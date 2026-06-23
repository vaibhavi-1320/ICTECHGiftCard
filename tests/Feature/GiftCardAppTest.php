<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\GiftCardVoucher;
use App\Models\GiftCardTransaction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\GiftCardMail;
use App\Jobs\ProcessShopifyOrderJob;
use Tests\TestCase;

class GiftCardAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_webhook_processing_creates_vouchers(): void
    {
        $shop = Shop::create([
            'shopify_shop_id' => '123',
            'shopify_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_token',
        ]);

        $giftCard = GiftCard::create([
            'shop_id' => $shop->id,
            'shopify_product_id' => 'prod-123',
            'shopify_product_variant_id' => 'var-123',
            'name' => '$50 Gift Card',
            'amount' => 50.00,
            'code_prefix' => 'GC',
            'validity_days' => 365,
            'active' => true,
        ]);

        // Create a voucher pool for the gift card
        for ($i = 0; $i < 25; $i++) {
            GiftCardVoucher::create([
                'gift_card_id' => $giftCard->id,
                'code' => 'GC-TESTPOOL-' . $i,
                'original_amount' => 50.00,
                'remaining_balance' => 50.00,
                'currency' => 'USD',
                'sender_name' => '',
                'recipient_name' => '',
                'recipient_email' => '',
                'personal_message' => '',
                'scheduled_send_date' => now()->format('Y-m-d'),
                'expires_at' => now()->addDays(365)->format('Y-m-d'),
                'status' => 'pending_issuance',
            ]);
        }

        // Mock Shopify API PriceRule & Discount Code creation calls
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/price_rules.json' => Http::response([
                'price_rule' => ['id' => 999]
            ], 200),
            'https://test-shop.myshopify.com/admin/api/*/price_rules/999/discount_codes.json' => Http::response([
                'discount_code' => ['id' => 888]
            ], 200),
        ]);

        $payload = [
            'id' => 'order-777',
            'financial_status' => 'paid',
            'currency' => 'USD',
            'customer' => ['id' => 'cust-123', 'email' => 'buyer@example.com'],
            'line_items' => [
                [
                    'id' => 'item-123',
                    'variant_id' => 'var-123',
                    'quantity' => 1,
                    'properties' => [
                        ['name' => 'Recipient Name', 'value' => 'Alice Recipient'],
                        ['name' => 'Recipient Email', 'value' => 'alice@example.com'],
                        ['name' => 'Sender Name', 'value' => 'Bob Sender'],
                        ['name' => 'Personal Message', 'value' => 'Happy birthday!'],
                        ['name' => 'Scheduled Send Date', 'value' => now()->format('Y-m-d')],
                    ]
                ]
            ]
        ];

        // Process webhook job
        $job = new ProcessShopifyOrderJob($payload, 'test-shop.myshopify.com');
        dispatch($job);

        // Verify voucher is assigned
        $voucher = GiftCardVoucher::where('shopify_order_id', 'order-777')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('Alice Recipient', $voucher->recipient_name);
        $this->assertSame('alice@example.com', $voucher->recipient_email);
        $this->assertSame('Bob Sender', $voucher->sender_name);
        $this->assertSame('unused', $voucher->status);
        $this->assertSame(999, $voucher->metadata['shopify_price_rule_id']);

        // Verify email was sent
        Mail::assertSent(GiftCardMail::class, function ($mail) use ($voucher) {
            return $mail->hasTo('alice@example.com') && $mail->voucher->id === $voucher->id;
        });
    }

    public function test_redemption_webhook_deducts_balance(): void
    {
        $shop = Shop::create([
            'shopify_shop_id' => '123',
            'shopify_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_token',
        ]);

        $giftCard = GiftCard::create([
            'shop_id' => $shop->id,
            'shopify_product_id' => 'prod-123',
            'shopify_product_variant_id' => 'var-123',
            'name' => '$50 Gift Card',
            'amount' => 50.00,
            'code_prefix' => 'GC',
            'validity_days' => 365,
            'active' => true,
        ]);

        $voucher = GiftCardVoucher::create([
            'gift_card_id' => $giftCard->id,
            'code' => 'GC-REDEEM-ME',
            'original_amount' => 50.00,
            'remaining_balance' => 50.00,
            'currency' => 'USD',
            'sender_name' => 'Bob',
            'recipient_name' => 'Alice',
            'recipient_email' => 'alice@example.com',
            'scheduled_send_date' => now()->format('Y-m-d'),
            'expires_at' => now()->addDays(365)->format('Y-m-d'),
            'status' => 'unused',
            'metadata' => ['shopify_price_rule_id' => 999]
        ]);

        // Mock Shopify PUT to update PriceRule
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/price_rules/999.json' => Http::response([], 200),
        ]);

        $payload = [
            'id' => 'order-888',
            'name' => '#1002',
            'financial_status' => 'paid',
            'customer' => ['id' => 'cust-456'],
            'discount_codes' => [
                ['code' => 'GC-REDEEM-ME', 'amount' => '20.00', 'type' => 'fixed_amount']
            ],
            'discount_applications' => [
                ['type' => 'discount_code', 'code' => 'GC-REDEEM-ME']
            ],
            'line_items' => [
                [
                    'id' => 'item-456',
                    'quantity' => 1,
                    'price' => '100.00',
                    'discount_allocations' => [
                        ['amount' => '20.00', 'discount_application_index' => 0]
                    ]
                ]
            ]
        ];

        $job = new ProcessShopifyOrderJob($payload, 'test-shop.myshopify.com');
        dispatch($job);

        // Verify remaining balance and transaction
        $voucher->refresh();
        $this->assertEquals(30.00, $voucher->remaining_balance);
        $this->assertSame('partially_used', $voucher->status);

        $tx = GiftCardTransaction::where('shopify_order_id', 'order-888')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(20.00, $tx->amount_used);
        $this->assertEquals(50.00, $tx->balance_before);
        $this->assertEquals(30.00, $tx->balance_after);
    }
}
