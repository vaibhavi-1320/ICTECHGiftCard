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
        // Note: we can process redemption regardless of financial status, but usually when order is created/paid
        $this->processRedemptions($shop, $orderId, $shopifyService);

        // 2. Voucher Issuance (only if order is paid)
        if ($financialStatus === 'paid') {
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

                // Pick a pending voucher from the pool, or generate on the fly
                $voucher = GiftCardVoucher::where('gift_card_id', $giftCard->id)
                    ->where('status', 'pending_issuance')
                    ->whereNull('shopify_order_id')
                    ->first();

                if (!$voucher) {
                    $prefix = strtoupper(trim($giftCard->code_prefix ?: 'GC'));
                    do {
                        $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
                    } while (GiftCardVoucher::where('code', $code)->exists());

                    $voucher = new GiftCardVoucher();
                    $voucher->gift_card_id = $giftCard->id;
                    $voucher->code = $code;
                    $voucher->original_amount = $giftCard->amount;
                    $voucher->remaining_balance = $giftCard->amount;
                    $voucher->currency = $this->payload['currency'] ?? 'USD';
                }

                // Parse storefront customization properties
                $properties = [];
                foreach ($lineItem['properties'] ?? [] as $prop) {
                    if (isset($prop['name']) && isset($prop['value'])) {
                        $properties[strtolower(trim($prop['name']))] = $prop['value'];
                    }
                }

                $recipientName = $properties['recipient name'] ?? $properties['recipient_name'] ?? '';
                $recipientEmail = $properties['recipient email'] ?? $properties['recipient_email'] ?? '';
                $senderName = $properties['sender name'] ?? $properties['sender_name'] ?? '';
                $personalMessage = $properties['personal message'] ?? $properties['personal_message'] ?? '';
                $scheduledSendDate = $properties['scheduled send date'] ?? $properties['scheduled_send_date'] ?? now()->format('Y-m-d');

                $voucher->shopify_order_id = $orderId;
                $voucher->shopify_order_line_item_id = $lineItemId;
                $voucher->shopify_customer_id = $customerId;
                $voucher->recipient_name = $recipientName ?: ($this->payload['shipping_address']['name'] ?? $this->payload['customer']['first_name'] ?? 'Recipient');
                $voucher->recipient_email = $recipientEmail ?: ($this->payload['customer']['email'] ?? '');
                $voucher->sender_name = $senderName ?: ($this->payload['customer']['first_name'] ?? 'Sender');
                $voucher->personal_message = $personalMessage;
                $voucher->scheduled_send_date = Carbon::parse($scheduledSendDate)->format('Y-m-d');
                $voucher->expires_at = now()->addDays($giftCard->validity_days ?: 365)->format('Y-m-d');
                $voucher->status = 'unused';
                $voucher->metadata = array_merge($voucher->metadata ?? [], ['item_index' => $k]);
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
                        'starts_at' => now()->toIso8601String(),
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

                // Send email immediately if scheduled for today or earlier
                $today = now()->format('Y-m-d');
                if ($voucher->recipient_email && Carbon::parse($voucher->scheduled_send_date)->format('Y-m-d') <= $today) {
                    try {
                        Mail::to($voucher->recipient_email)->send(new GiftCardMail($voucher));
                        $voucher->sent_at = now();
                        $voucher->save();
                    } catch (\Throwable $e) {
                        Log::error("ProcessShopifyOrderJob: Failed to send gift card email for voucher {$voucher->id}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function processRedemptions(Shop $shop, string $orderId, ShopifyService $shopifyService): void
    {
        $customerId = $this->payload['customer']['id'] ?? null;
        $orderNumber = $this->payload['name'] ?? $this->payload['order_number'] ?? null;

        foreach ($this->payload['discount_codes'] ?? [] as $dc) {
            $code = strtoupper(trim($dc['code'] ?? ''));
            if (empty($code)) {
                continue;
            }

            $voucher = GiftCardVoucher::where('code', $code)
                ->whereIn('status', ['unused', 'partially_used'])
                ->first();

            if (!$voucher) {
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

                GiftCardTransaction::create([
                    'voucher_id' => $voucher->id,
                    'shopify_order_id' => $orderId,
                    'shopify_customer_id' => $customerId,
                    'amount_used' => $amountUsed,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter
                ]);

                $voucher->remaining_balance = $balanceAfter;
                $voucher->used_in_order_number = $orderNumber;
                $voucher->status = $balanceAfter <= 0.0 ? 'used' : 'partially_used';
                $voucher->save();

                // Sync new balance or delete discount code on Shopify
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
