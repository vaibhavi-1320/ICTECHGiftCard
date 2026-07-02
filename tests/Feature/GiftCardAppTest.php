<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\GiftCardVoucher;
use App\Models\GiftCardTransaction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Mail\GiftCardMail;
use App\Mail\RecipientGiftCardMail;
use App\Mail\PurchaserConfirmationMail;
use App\Jobs\ProcessShopifyOrderJob;
use App\Jobs\SendGiftCardEmailJob;
use Tests\TestCase;

class GiftCardAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake([SendGiftCardEmailJob::class]);
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

        // Verify job was dispatched
        Queue::assertPushed(SendGiftCardEmailJob::class, function ($job) use ($voucher) {
            return $job->voucherId === $voucher->id;
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

    public function test_webhook_processing_generates_voucher_on_the_fly_when_pool_is_empty(): void
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
            'id' => 'order-777-fly',
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

        // Verify voucher is created on-the-fly and assigned
        $voucher = GiftCardVoucher::where('shopify_order_id', 'order-777-fly')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('Alice Recipient', $voucher->recipient_name);
        $this->assertSame('alice@example.com', $voucher->recipient_email);
        $this->assertSame('Bob Sender', $voucher->sender_name);
        $this->assertSame('unused', $voucher->status);
        $this->assertStringStartsWith('GC-', $voucher->code);
    }

    public function test_send_gift_card_email_job_sends_emails_correctly(): void
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

        $giftCardOrder = \App\Models\GiftCardOrder::create([
            'shopify_order_id' => 'order-999',
            'shopify_order_number' => '1001',
            'shopify_customer_id' => 'cust-123',
            'customer_name' => 'Buyer Name',
            'customer_email' => 'buyer@example.com',
            'shopify_product_id' => 'prod-123',
            'shopify_variant_id' => 'var-123',
            'gift_card_product_name' => '$50 Gift Card',
            'amount' => 50.00,
            'recipient_name' => 'Alice Recipient',
            'recipient_email' => 'alice@example.com',
            'sender_name' => 'Bob Sender',
            'personal_message' => 'Happy birthday!',
            'delivery_date' => now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        $voucher = GiftCardVoucher::create([
            'gift_card_id' => $giftCard->id,
            'gift_card_order_id' => $giftCardOrder->id,
            'code' => 'GC-TEST-123456',
            'original_amount' => 50.00,
            'remaining_balance' => 50.00,
            'currency' => 'USD',
            'sender_name' => 'Bob Sender',
            'recipient_name' => 'Alice Recipient',
            'recipient_email' => 'alice@example.com',
            'personal_message' => 'Happy birthday!',
            'scheduled_send_date' => now()->format('Y-m-d'),
            'expires_at' => now()->addDays(365)->format('Y-m-d'),
            'status' => 'unused',
        ]);

        // Mock Shopify shop.json request
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/shop.json' => Http::response([
                'shop' => ['name' => 'Test Premium Shop', 'domain' => 'test-shop.myshopify.com']
            ], 200),
        ]);

        // Run the job synchronously by calling handler
        $job = new SendGiftCardEmailJob($voucher->id);
        app()->call([$job, 'handle']);

        // Assert recipient mail sent
        Mail::assertSent(RecipientGiftCardMail::class, function ($mail) use ($voucher) {
            return $mail->hasTo('alice@example.com') && $mail->voucher->id === $voucher->id;
        });

        // Assert purchaser mail sent (since recipient_email !== customer_email)
        Mail::assertSent(PurchaserConfirmationMail::class, function ($mail) use ($voucher) {
            return $mail->hasTo('buyer@example.com') && $mail->voucher->id === $voucher->id;
        });

        // Assert database updated
        $voucher->refresh();
        $this->assertSame('delivered', $voucher->status);
        $this->assertNotNull($voucher->sent_at);
        $this->assertNotNull($voucher->metadata['secure_token']);

        $giftCardOrder->refresh();
        $this->assertSame('completed', $giftCardOrder->status);
    }

    public function test_future_scheduled_gift_card_sends_notification_not_code_job(): void
    {
        Queue::fake([SendGiftCardEmailJob::class]);
        Mail::fake();

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

        // Mock Shopify shop.json request
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/price_rules.json' => Http::response([
                'price_rule' => ['id' => 999]
            ], 201),
            'https://test-shop.myshopify.com/admin/api/*/price_rules/999/discount_codes.json' => Http::response([
                'discount_code' => ['id' => 888]
            ], 201),
            'https://test-shop.myshopify.com/admin/api/*/shop.json' => Http::response([
                'shop' => ['name' => 'Test Premium Shop', 'domain' => 'test-shop.myshopify.com']
            ], 200),
        ]);

        $payload = [
            'id' => 'order-777',
            'financial_status' => 'paid',
            'name' => '#1001',
            'currency' => 'USD',
            'customer' => [
                'id' => 'cust-456',
                'first_name' => 'John',
                'last_name' => 'Buyer',
                'email' => 'buyer@example.com',
            ],
            'line_items' => [
                [
                    'id' => 'item-888',
                    'product_id' => 'prod-123',
                    'variant_id' => 'var-123',
                    'title' => '$50 Gift Card',
                    'properties' => [
                        ['name' => 'Recipient Name', 'value' => 'Alice Recipient'],
                        ['name' => 'Recipient Email', 'value' => 'alice@example.com'],
                        ['name' => 'Sender Name', 'value' => 'Bob Sender'],
                        ['name' => 'Personal Message', 'value' => 'Happy birthday!'],
                        ['name' => 'Scheduled Send Date', 'value' => now()->addDays(5)->format('Y-m-d')],
                    ]
                ]
            ]
        ];

        // Process webhook job
        $job = new ProcessShopifyOrderJob($payload, 'test-shop.myshopify.com');
        dispatch($job);

        // Verify voucher is assigned and properties are correct
        $voucher = GiftCardVoucher::where('shopify_order_id', 'order-777')->first();
        $this->assertNotNull($voucher);
        $this->assertSame(now()->addDays(5)->format('Y-m-d'), $voucher->scheduled_send_date->format('Y-m-d'));

        // Verify real code job was NOT dispatched (since it is scheduled in the future)
        Queue::assertNotPushed(SendGiftCardEmailJob::class);

        // Verify scheduled notification email was NOT sent to the recipient (to keep it a surprise)
        Mail::assertNotSent(\App\Mail\RecipientScheduledGiftCardMail::class);

        // Verify scheduled confirmation email WAS sent immediately to the purchaser
        Mail::assertSent(\App\Mail\PurchaserScheduledConfirmationMail::class, function ($mail) use ($voucher) {
            return $mail->hasTo('buyer@example.com') && $mail->voucher->id === $voucher->id;
        });
    }

    public function test_gift_card_cannot_be_deleted_if_already_purchased(): void
    {
        $shop = Shop::create([
            'shopify_shop_id' => '123',
            'shopify_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_token',
        ]);

        $giftCard = GiftCard::create([
            'shop_id' => $shop->id,
            'shopify_product_id' => 'prod-999',
            'shopify_product_variant_id' => 'var-999',
            'name' => 'Purchased Gift Card',
            'amount' => 100.00,
            'code_prefix' => 'GC',
            'validity_days' => 365,
            'active' => true,
        ]);

        // Simulating a purchase (voucher exists)
        GiftCardVoucher::create([
            'gift_card_id' => $giftCard->id,
            'code' => 'GC-SOLD-12345',
            'original_amount' => 100.00,
            'remaining_balance' => 100.00,
            'currency' => 'USD',
            'sender_name' => 'Buyer',
            'recipient_name' => 'Recipient',
            'recipient_email' => 'recipient@example.com',
            'scheduled_send_date' => now()->format('Y-m-d'),
            'expires_at' => now()->addDays(365)->format('Y-m-d'),
            'status' => 'unused',
        ]);

        $response = $this->delete("/shopify/gift-cards/{$giftCard->id}?shop=test-shop.myshopify.com");

        $response->assertRedirect(route('shopify.gift-cards.index', ['shop' => 'test-shop.myshopify.com']));
        $response->assertSessionHas('error', 'This gift card has already been purchased and cannot be deleted.');
        
        $this->assertDatabaseHas('gift_cards', ['id' => $giftCard->id]);
    }

    public function test_gift_card_can_be_deleted_if_not_purchased(): void
    {
        $shop = Shop::create([
            'shopify_shop_id' => '123',
            'shopify_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_token',
        ]);

        $giftCard = GiftCard::create([
            'shop_id' => $shop->id,
            'shopify_product_id' => 'prod-999',
            'shopify_product_variant_id' => 'var-999',
            'name' => 'Unpurchased Gift Card',
            'amount' => 100.00,
            'code_prefix' => 'GC',
            'validity_days' => 365,
            'active' => true,
        ]);

        // Mock Shopify delete product API
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/products/prod-999.json' => Http::response([], 200),
        ]);

        $response = $this->delete("/shopify/gift-cards/{$giftCard->id}?shop=test-shop.myshopify.com");

        $response->assertRedirect(route('shopify.gift-cards.index', ['shop' => 'test-shop.myshopify.com']));
        $response->assertSessionHas('status', 'Gift card deleted.');
        
        $this->assertDatabaseMissing('gift_cards', ['id' => $giftCard->id]);
    }

    public function test_storefront_gift_card_validation_success(): void
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
            'name' => 'Test Gift Card',
            'amount' => 100.00,
            'code_prefix' => 'GC',
            'validity_days' => 365,
            'active' => true,
        ]);

        $voucher = GiftCardVoucher::create([
            'gift_card_id' => $giftCard->id,
            'shopify_order_id' => 'order-123',
            'code' => 'GC-TEST1234',
            'original_amount' => 100.00,
            'remaining_balance' => 100.00,
            'currency' => 'USD',
            'sender_name' => 'Buyer',
            'recipient_name' => 'Recipient',
            'recipient_email' => 'recipient@example.com',
            'scheduled_send_date' => now()->format('Y-m-d'),
            'expires_at' => now()->addDays(365)->format('Y-m-d'),
            'status' => 'unused',
        ]);

        // Mock Shopify API calls for price rule and discount code creation
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/price_rules.json' => Http::response([
                'price_rule' => [
                    'id' => 998877,
                    'title' => 'GC-TEMP-GC-TEST1234-ABCDEF'
                ]
            ], 200),
            'https://test-shop.myshopify.com/admin/api/*/price_rules/998877/discount_codes.json' => Http::response([
                'discount_code' => [
                    'id' => 112233,
                    'code' => 'GC-TEMP-GC-TEST1234-ABCDEF'
                ]
            ], 200),
        ]);

        $response = $this->postJson("/storefront/gift-cards/validate?shop=test-shop.myshopify.com", [
            'code' => 'GC-TEST1234',
            'cart_total' => 50.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'price_rule_id' => 998877,
            'discount_amount' => 50.00,
            'original_balance' => 100.00,
            'remaining_balance' => 50.00,
        ]);
        $this->assertStringContainsString('GC-TEMP-GC-TEST1234-', $response->json('discount_code'));
    }

    public function test_storefront_gift_card_validation_invalid_code(): void
    {
        $shop = Shop::create([
            'shopify_shop_id' => '123',
            'shopify_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_token',
        ]);

        $response = $this->postJson("/storefront/gift-cards/validate?shop=test-shop.myshopify.com", [
            'code' => 'NON-EXISTENT',
            'cart_total' => 50.00,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid, expired, or fully used gift card.',
        ]);
    }

    public function test_storefront_gift_card_removal(): void
    {
        $shop = Shop::create([
            'shopify_shop_id' => '123',
            'shopify_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_token',
        ]);

        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/price_rules/998877.json' => Http::response([], 200),
        ]);

        $response = $this->postJson("/storefront/gift-cards/remove?shop=test-shop.myshopify.com", [
            'price_rule_id' => 998877,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_webhook_order_paid_processes_redemption_with_temp_code(): void
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
            'name' => 'Test Gift Card',
            'amount' => 100.00,
            'code_prefix' => 'GC',
            'validity_days' => 365,
            'active' => true,
        ]);

        $voucher = GiftCardVoucher::create([
            'gift_card_id' => $giftCard->id,
            'shopify_order_id' => 'order-123',
            'code' => 'GC-REDEEM123',
            'original_amount' => 100.00,
            'remaining_balance' => 100.00,
            'currency' => 'USD',
            'sender_name' => 'Buyer',
            'recipient_name' => 'Recipient',
            'recipient_email' => 'recipient@example.com',
            'scheduled_send_date' => now()->format('Y-m-d'),
            'expires_at' => now()->addDays(365)->format('Y-m-d'),
            'status' => 'unused',
        ]);

        // Mock Shopify API to delete the temporary Price Rule
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/price_rules/998877.json' => Http::response([], 200),
        ]);

        // Order webhook payload
        $payload = [
            'id' => 450,
            'name' => '#1005',
            'total_price' => '250.00',
            'discount_codes' => [
                [
                    'code' => 'GC-TEMP-GC-REDEEM123-ABCDEF',
                    'amount' => '40.00',
                    'type' => 'fixed_amount'
                ]
            ],
            'discount_applications' => [
                [
                    'type' => 'discount_code',
                    'code' => 'GC-TEMP-GC-REDEEM123-ABCDEF',
                    'value' => '40.00',
                    'value_type' => 'fixed_amount'
                ]
            ],
            'line_items' => [
                [
                    'id' => 777,
                    'price' => '100.00',
                    'quantity' => 1,
                    'discount_allocations' => [
                        [
                            'amount' => '40.00',
                            'discount_application_index' => 0
                        ]
                    ]
                ]
            ],
            'note_attributes' => [
                [
                    'name' => '_gift_card_code',
                    'value' => 'GC-REDEEM123'
                ],
                [
                    'name' => '_gift_card_price_rule_id',
                    'value' => '998877'
                ]
            ],
            'customer' => [
                'id' => 999111,
                'email' => 'customer@example.com'
            ]
        ];

        // Process webhook order paid
        $job = new \App\Jobs\ProcessShopifyOrderJob($payload, $shop->shopify_domain);
        $job->handle(app(\App\Services\Shopify\ShopifyService::class));

        // Assert voucher remaining balance updated
        $voucher->refresh();
        $this->assertEquals(60.00, (float) $voucher->remaining_balance);
        $this->assertEquals('partially_used', $voucher->status);

        // Assert Transaction was logged
        $this->assertDatabaseHas('gift_card_transactions', [
            'voucher_id' => $voucher->id,
            'shopify_order_id' => '450',
            'amount_used' => 40.00,
            'balance_before' => 100.00,
            'balance_after' => 60.00,
        ]);

        // Assert Audit Log was created
        $this->assertDatabaseHas('gift_card_audit_logs', [
            'voucher_id' => $voucher->id,
            'action' => 'redemption',
            'reason' => 'Redeemed in order #1005',
        ]);
    }
}

