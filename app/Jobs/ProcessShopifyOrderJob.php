<?php

namespace App\Jobs;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\GiftCardVoucher;
use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use App\Mail\GiftCardMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessShopifyOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $payload, public ?string $shopDomain = null)
    {
    }

    public function handle(ShopifyService $shopifyService): void
    {
        $domain = $this->shopDomain ?? $this->payload['shop_domain'] ?? null;
        if (!$domain) {
            Log::error("ProcessShopifyOrderJob: Missing shop domain.");
            return;
        }

        $shop = Shop::where('shopify_domain', $domain)->first();
        if (!$shop) {
            Log::error("ProcessShopifyOrderJob: Shop {$domain} not found in database.");
            return;
        }

        $financialStatus = $this->payload['financial_status'] ?? '';
        $orderId = (string) ($this->payload['id'] ?? '');

        if (empty($orderId)) {
            Log::error("ProcessShopifyOrderJob: Missing order ID in payload.");
            return;
        }

        // 1. Voucher Redemption (if order contains discount code matching our vouchers)
        $this->processRedemptions($shop, $orderId, $shopifyService);

        // 2. Voucher Issuance
        // Process for 'paid' orders AND 'pending' orders (e.g. Cash on Delivery).
        // The idempotency check inside processIssuance prevents duplicate vouchers
        // if this webhook fires multiple times for the same order.
        $issuanceStatuses = ['paid', 'pending', 'authorized'];
        if (in_array($financialStatus, $issuanceStatuses)) {
            $this->processIssuance($shop, $orderId, $shopifyService);
        }
    }

    private function processIssuance(Shop $shop, string $orderId, ShopifyService $shopifyService): void
    {
        $customerId = $this->payload['customer']['id'] ?? null;

        foreach ($this->payload['line_items'] ?? [] as $lineItem) {
            $variantId = $lineItem['variant_id'] ?? null;
            if (!$variantId) {
                continue;
            }

            $giftCard = GiftCard::where('shopify_product_variant_id', $variantId)
                ->where('shop_id', $shop->id)
                ->first();

            if (!$giftCard) {
                continue;
            }

            $lineItemId = (string) ($lineItem['id'] ?? '');
            $qty = (int) ($lineItem['quantity'] ?? 1);

            for ($k = 0; $k < $qty; $k++) {
                // Check idempotency for this line item and quantity index
                $exists = GiftCardVoucher::where('shopify_order_id', $orderId)
                    ->where('shopify_order_line_item_id', $lineItemId)
                    ->whereJsonContains('metadata->item_index', $k)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Parse storefront customization properties
                // Note: keys starting with _ are hidden by Shopify on checkout but still present in webhook
                $properties = [];
                foreach ($lineItem['properties'] ?? [] as $prop) {
                    if (isset($prop['name']) && isset($prop['value'])) {
                        // Normalize: strip leading underscore for matching, keep original value
                        $key = strtolower(trim(ltrim($prop['name'], '_')));
                        $properties[$key] = $prop['value'];
                    }
                }

                $recipientName      = $properties['recipient name']      ?? $properties['recipient_name']      ?? '';
                $recipientEmail     = $properties['recipient email']     ?? $properties['recipient_email']     ?? '';
                $senderName         = $properties['sender name']         ?? $properties['sender_name']         ?? '';
                $personalMessage    = $properties['message']             ?? $properties['personal message']    ?? $properties['personal_message'] ?? '';
                $deliveryMethod     = $properties['delivery method']     ?? $properties['delivery_method']     ?? 'print';
                $templateName       = $properties['template name']       ?? $properties['template_name']       ?? '';
                $templateImageUrl   = $properties['template image']      ?? $properties['template_image']      ?? '';
                $scheduledSendDate  = $properties['scheduled send date'] ?? $properties['scheduled_send_date'] ?? now()->format('Y-m-d');

                // Extract customer and order properties
                $orderNumber = $this->payload['name'] ?? $this->payload['order_number'] ?? '';
                $firstName = $this->payload['customer']['first_name'] ?? '';
                $lastName = $this->payload['customer']['last_name'] ?? '';
                $customerName = trim($firstName . ' ' . $lastName);
                $customerEmail = $this->payload['customer']['email'] ?? '';

                // Create the Gift Card Order Record (Step 3)
                $giftCardOrder = new \App\Models\GiftCardOrder();
                $giftCardOrder->shopify_order_id = $orderId;
                $giftCardOrder->shopify_order_number = $orderNumber;
                $giftCardOrder->shopify_customer_id = $customerId;
                $giftCardOrder->customer_name = $customerName ?: 'Customer';
                $giftCardOrder->customer_email = $customerEmail;
                $giftCardOrder->shopify_product_id = (string) ($lineItem['product_id'] ?? '');
                $giftCardOrder->shopify_variant_id = (string) $variantId;
                $giftCardOrder->gift_card_product_name = $lineItem['title'] ?? 'Gift Card';
                $giftCardOrder->amount = $giftCard->amount;
                $giftCardOrder->template_name = $templateName;
                $giftCardOrder->recipient_name = $recipientName ?: ($this->payload['shipping_address']['name'] ?? $firstName ?: 'Recipient');
                $giftCardOrder->recipient_email = $recipientEmail ?: $customerEmail;
                $giftCardOrder->sender_name = $senderName ?: ($firstName ?: 'Sender');
                $giftCardOrder->personal_message = $personalMessage;
                $giftCardOrder->delivery_date = Carbon::parse($scheduledSendDate)->format('Y-m-d');
                $giftCardOrder->status = 'completed';
                $giftCardOrder->save();

                // Pick a pending voucher from the pool, or generate on the fly
                $voucher = GiftCardVoucher::where('gift_card_id', $giftCard->id)
                    ->where('status', 'pending_issuance')
                    ->whereNull('shopify_order_id')
                    ->first();

                if (!$voucher) {
                    $prefix = strtoupper(trim($giftCard->code_prefix ?: ''));
                    do {
                        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        $randomPart = '';
                        for ($j = 0; $j < 12; $j++) {
                            $randomPart .= $characters[random_int(0, 35)];
                        }
                        $code = $prefix !== '' ? ($prefix . '-' . $randomPart) : $randomPart;
                    } while (GiftCardVoucher::where('code', $code)->exists());

                    $voucher = new GiftCardVoucher();
                    $voucher->gift_card_id = $giftCard->id;
                    $voucher->code = $code;
                    $voucher->original_amount = $giftCard->amount;
                    $voucher->remaining_balance = $giftCard->amount;
                    $voucher->currency = $this->payload['currency'] ?? 'USD';
                }

                // Associate GiftCardOrder with the Voucher (Step 4)
                $voucher->gift_card_order_id = $giftCardOrder->id;
                $voucher->shopify_order_id = $orderId;
                $voucher->shopify_order_line_item_id = $lineItemId;
                $voucher->shopify_customer_id = $customerId;
                $voucher->recipient_name = $giftCardOrder->recipient_name;
                $voucher->recipient_email = $giftCardOrder->recipient_email;
                $voucher->sender_name = $giftCardOrder->sender_name;
                $voucher->personal_message = $personalMessage;
                $voucher->scheduled_send_date = Carbon::parse($scheduledSendDate)->format('Y-m-d');
                $voucher->expires_at = now()->addDays((int) ($giftCard->validity_days ?: 365))->format('Y-m-d');
                $voucher->status = 'unused';

                // Resolve local template image path from URL if possible
                $templateLocalPath = '';
                if ($templateImageUrl) {
                    // Extract relative path from URL like .../storage/gift-card-templates/xxx.png
                    if (preg_match('#/storage/(.+)$#', $templateImageUrl, $m)) {
                        $templateLocalPath = $m[1]; // e.g. gift-card-templates/xxx.png
                    }
                }

                $voucher->metadata = array_merge($voucher->metadata ?? [], [
                    'item_index'         => $k,
                    'delivery_method'    => $deliveryMethod,
                    'template_name'      => $templateName,
                    'template_image_url' => $templateLocalPath ?: $templateImageUrl,
                ]);
                $voucher->save();

                // Create Shopify Price Rule
                $priceRulePayload = [
                    'price_rule' => [
                        'title' => $voucher->code,
                        'target_type' => 'line_item',
                        'target_selection' => 'all',
                        'allocation_method' => 'across',
                        'value_type' => 'fixed_amount',
                        'value' => '-' . number_format((float) $voucher->original_amount, 2, '.', ''),
                        'customer_selection' => 'all',
                        'starts_at' => Carbon::parse($voucher->scheduled_send_date)->startOfDay()->toIso8601String(),
                        'ends_at' => Carbon::parse($voucher->expires_at)->endOfDay()->toIso8601String(),
                        'usage_limit' => null,
                    ]
                ];

                try {
                    $prResponse = $shopifyService->api($shop, 'POST', 'price_rules.json', $priceRulePayload);
                    if ($prResponse->successful()) {
                        $prData = $prResponse->json();
                        $priceRuleId = $prData['price_rule']['id'];

                        // Create Shopify Discount Code
                        $dcPayload = [
                            'discount_code' => [
                                'code' => $voucher->code
                            ]
                        ];
                        $dcResponse = $shopifyService->api($shop, 'POST', "price_rules/{$priceRuleId}/discount_codes.json", $dcPayload);
                        if ($dcResponse->successful()) {
                            $voucher->metadata = array_merge($voucher->metadata ?? [], ['shopify_price_rule_id' => $priceRuleId]);
                            $voucher->save();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("ProcessShopifyOrderJob: Failed to create Price Rule or Discount Code on Shopify for code {$voucher->code}: " . $e->getMessage());
                }

                // Dispatch email sending job immediately if scheduled for today or earlier
                $today = now()->format('Y-m-d');
                if ($voucher->recipient_email && Carbon::parse($voucher->scheduled_send_date)->format('Y-m-d') <= $today) {
                    \App\Jobs\SendGiftCardEmailJob::dispatch($voucher->id);
                } else {
                    // Send scheduled confirmation email ONLY to the purchaser (buyer)
                    $purchEmail = trim(strtolower($customerEmail));
                    if (!empty($purchEmail)) {
                        try {
                            $shopName = $shop->shopify_domain;
                            $shopLogoUrl = null;
                            try {
                                $response = $shopifyService->api($shop, 'GET', 'shop.json');
                                if ($response->successful()) {
                                    $shopData = $response->json('shop');
                                    $shopName = $shopData['name'] ?? $shop->shopify_domain;
                                }
                            } catch (\Throwable $se) {
                                Log::error("ProcessShopifyOrderJob: Failed to fetch shop details: " . $se->getMessage());
                            }

                            Mail::to($purchEmail)->send(new \App\Mail\PurchaserScheduledConfirmationMail(
                                $voucher,
                                $shopName,
                                $shopLogoUrl
                            ));
                        } catch (\Throwable $e) {
                            Log::error("ProcessShopifyOrderJob: Failed to send scheduled confirmation email for voucher {$voucher->id}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    private function processRedemptions(Shop $shop, string $orderId, ShopifyService $shopifyService): void
    {
        $customerId = $this->payload['customer']['id'] ?? null;
        $customerFirstName = $this->payload['customer']['first_name'] ?? '';
        $customerLastName = $this->payload['customer']['last_name'] ?? '';
        $customerName = trim($customerFirstName . ' ' . $customerLastName) ?: null;
        $customerEmail = $this->payload['customer']['email'] ?? null;
        $orderNumber = $this->payload['name'] ?? $this->payload['order_number'] ?? null;

        // Extract attributes from note_attributes
        $attrOriginalCode = null;
        $tempPriceRuleId = null;
        foreach ($this->payload['note_attributes'] ?? [] as $attr) {
            if (($attr['name'] ?? '') === '_gift_card_code') {
                $attrOriginalCode = strtoupper(trim($attr['value'] ?? ''));
            }
            if (($attr['name'] ?? '') === '_gift_card_price_rule_id') {
                $tempPriceRuleId = trim($attr['value'] ?? '');
            }
        }

        foreach ($this->payload['discount_codes'] ?? [] as $dc) {
            $code = strtoupper(trim($dc['code'] ?? ''));
            if (empty($code)) {
                continue;
            }

            $extractedCode = $code;
            $isTemporary = false;

            if (str_starts_with($code, 'GC-TEMP-')) {
                $isTemporary = true;
                if (preg_match('/^GC-TEMP-(.+)-[A-Z0-9]{6}$/i', $code, $matches)) {
                    $extractedCode = strtoupper($matches[1]);
                }
            }

            $lookupCode = $attrOriginalCode ?: $extractedCode;

            $voucher = GiftCardVoucher::where('code', $lookupCode)
                ->whereIn('status', ['unused', 'delivered', 'partially_used'])
                ->first();

            if (!$voucher) {
                continue;
            }

            if (Carbon::parse($voucher->scheduled_send_date)->format('Y-m-d') > now()->format('Y-m-d')) {
                Log::warning("ProcessShopifyOrderJob: Voucher {$voucher->code} cannot be redeemed because its scheduled send date {$voucher->scheduled_send_date} is in the future.");
                continue;
            }

            // Check if transaction has already been recorded
            $txExists = GiftCardTransaction::where('shopify_order_id', $orderId)
                ->where('voucher_id', $voucher->id)
                ->exists();

            if ($txExists) {
                continue;
            }

            // Sum allocations to calculate exact amount used
            $amountUsed = 0.0;
            $appIndex = null;
            foreach ($this->payload['discount_applications'] ?? [] as $idx => $app) {
                if (($app['type'] ?? '') === 'discount_code' && strtolower($app['code'] ?? '') === strtolower($code)) {
                    $appIndex = $idx;
                    break;
                }
            }

            if ($appIndex !== null) {
                foreach ($this->payload['line_items'] ?? [] as $item) {
                    foreach ($item['discount_allocations'] ?? [] as $allocation) {
                        if (($allocation['discount_application_index'] ?? null) == $appIndex) {
                            $amountUsed += (float) ($allocation['amount'] ?? 0.0);
                        }
                    }
                }
            }

            if ($amountUsed <= 0.0) {
                $amountUsed = (float) ($dc['amount'] ?? 0.0);
            }

            if ($amountUsed > 0.0) {
                $balanceBefore = $voucher->remaining_balance;
                $balanceAfter = max(0.0, $balanceBefore - $amountUsed);
                $oldStatus = $voucher->status;
                $newStatus = $balanceAfter <= 0.0 ? 'used' : 'partially_used';

                // Log the transaction
                GiftCardTransaction::create([
                    'voucher_id' => $voucher->id,
                    'shopify_order_id' => $orderId,
                    'shopify_customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'amount_used' => $amountUsed,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter
                ]);

                // Update the voucher
                $voucher->remaining_balance = $balanceAfter;
                $voucher->used_in_order_number = $orderNumber;
                $voucher->status = $newStatus;
                $voucher->save();

                // Save Audit Log
                \App\Models\GiftCardAuditLog::create([
                    'voucher_id' => $voucher->id,
                    'admin_user_id' => null,
                    'action' => 'redemption',
                    'old_value' => [
                        'remaining_balance' => (float) $balanceBefore,
                        'status' => $oldStatus
                    ],
                    'new_value' => [
                        'remaining_balance' => (float) $balanceAfter,
                        'status' => $newStatus
                    ],
                    'reason' => 'Redeemed in order ' . ($orderNumber ?: $orderId)
                ]);

                // Cleanup Shopify PriceRule
                if ($isTemporary) {
                    // For temporary codes, delete the temporary Price Rule
                    $ruleToDelete = $tempPriceRuleId ?: ($voucher->metadata['shopify_price_rule_id'] ?? null);
                    if ($ruleToDelete) {
                        try {
                            $shopifyService->api($shop, 'DELETE', "price_rules/{$ruleToDelete}.json");
                        } catch (\Throwable $e) {
                            Log::error("ProcessShopifyOrderJob: Failed to delete temporary Price Rule {$ruleToDelete}: " . $e->getMessage());
                        }
                    }
                } else {
                    // For permanent codes, update or delete the Shopify Price Rule
                    $priceRuleId = $voucher->metadata['shopify_price_rule_id'] ?? null;
                    if ($priceRuleId) {
                        try {
                            if ($balanceAfter <= 0.0) {
                                $shopifyService->api($shop, 'DELETE', "price_rules/{$priceRuleId}.json");
                            } else {
                                $updatePayload = [
                                    'price_rule' => [
                                        'id' => $priceRuleId,
                                        'value' => '-' . number_format($balanceAfter, 2, '.', ''),
                                    ]
                                ];
                                $shopifyService->api($shop, 'PUT', "price_rules/{$priceRuleId}.json", $updatePayload);
                            }
                        } catch (\Throwable $e) {
                            Log::error("ProcessShopifyOrderJob: Failed to update/delete Shopify Price Rule {$priceRuleId} after redemption: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
