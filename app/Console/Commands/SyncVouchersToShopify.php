<?php

namespace App\Console\Commands;

use App\Models\GiftCardVoucher;
use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncVouchersToShopify extends Command
{
    protected $signature = 'gift-cards:sync-vouchers';
    protected $description = 'Create missing Shopify Price Rules and Discount Codes for existing vouchers';

    public function __construct(private readonly ShopifyService $shopifyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $vouchers = GiftCardVoucher::all();
        $this->info("Found " . $vouchers->count() . " total vouchers in database.");

        $syncedCount = 0;
        foreach ($vouchers as $voucher) {
            $metadata = $voucher->metadata ?? [];
            if (isset($metadata['shopify_price_rule_id'])) {
                $this->line("Voucher [{$voucher->code}] already has Shopify Price Rule ID: {$metadata['shopify_price_rule_id']}. Skipping.");
                continue;
            }

            if ($voucher->remaining_balance <= 0) {
                $this->line("Voucher [{$voucher->code}] has no remaining balance. Skipping.");
                continue;
            }

            $giftCard = $voucher->giftCard;
            if (!$giftCard) {
                $this->error("Gift card definition not found for voucher [{$voucher->code}]. Skipping.");
                continue;
            }

            $shop = $giftCard->shop;
            if (!$shop) {
                $this->error("Shop not found for voucher [{$voucher->code}]. Skipping.");
                continue;
            }

            $this->info("Syncing voucher [{$voucher->code}] (Balance: {$voucher->remaining_balance}) to shop [{$shop->shopify_domain}]...");

            // Create Shopify Price Rule
            $priceRulePayload = [
                'price_rule' => [
                    'title' => $voucher->code,
                    'target_type' => 'line_item',
                    'target_selection' => 'all',
                    'allocation_method' => 'across',
                    'value_type' => 'fixed_amount',
                    'value' => '-' . number_format((float) $voucher->remaining_balance, 2, '.', ''),
                    'customer_selection' => 'all',
                    'starts_at' => Carbon::parse($voucher->scheduled_send_date)->startOfDay()->toIso8601String(),
                    'ends_at' => Carbon::parse($voucher->expires_at)->endOfDay()->toIso8601String(),
                    'usage_limit' => null,
                ]
            ];

            try {
                $prResponse = $this->shopifyService->api($shop, 'POST', 'price_rules.json', $priceRulePayload);
                if ($prResponse->successful()) {
                    $prData = $prResponse->json();
                    $priceRuleId = $prData['price_rule']['id'];

                    // Create Shopify Discount Code
                    $dcPayload = [
                        'discount_code' => [
                            'code' => $voucher->code
                        ]
                    ];
                    $dcResponse = $this->shopifyService->api($shop, 'POST', "price_rules/{$priceRuleId}/discount_codes.json", $dcPayload);
                    if ($dcResponse->successful()) {
                        $voucher->metadata = array_merge($metadata, ['shopify_price_rule_id' => $priceRuleId]);
                        $voucher->save();
                        $this->info("Successfully synced voucher [{$voucher->code}] with Price Rule ID [{$priceRuleId}].");
                        $syncedCount++;
                    } else {
                        $this->error("Failed to create Shopify Discount Code: " . $dcResponse->body());
                    }
                } else {
                    $this->error("Failed to create Shopify Price Rule: " . $prResponse->body());
                }
            } catch (\Throwable $e) {
                $this->error("Exception syncing voucher [{$voucher->code}]: " . $e->getMessage());
            }
        }

        $this->info("Done. Synced {$syncedCount} vouchers.");
        return self::SUCCESS;
    }
}
