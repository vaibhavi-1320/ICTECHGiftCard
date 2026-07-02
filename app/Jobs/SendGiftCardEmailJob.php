<?php

namespace App\Jobs;

use App\Models\GiftCardVoucher;
use App\Services\GiftCardPdfService;
use App\Services\Shopify\ShopifyService;
use App\Mail\RecipientGiftCardMail;
use App\Mail\PurchaserConfirmationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendGiftCardEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 360]; // Retry after 1m, 3m, 6m

    public function __construct(public int $voucherId)
    {
    }

    public function handle(ShopifyService $shopifyService, GiftCardPdfService $pdfService): void
    {
        $voucher = GiftCardVoucher::with(['giftCard.shop', 'giftCardOrder'])->find($this->voucherId);

        if (!$voucher) {
            Log::error("SendGiftCardEmailJob: Voucher ID {$this->voucherId} not found.");
            return;
        }

        $shop = $voucher->giftCard?->shop;
        if (!$shop) {
            Log::error("SendGiftCardEmailJob: Shop association not found for voucher ID {$this->voucherId}.");
            return;
        }

        $giftCardOrder = $voucher->giftCardOrder;
        if (!$giftCardOrder) {
            Log::error("SendGiftCardEmailJob: Gift Card Order not found for voucher ID {$this->voucherId}.");
            return;
        }

        // 1. Ensure secure_token exists in metadata
        $metadata = $voucher->metadata ?? [];
        if (empty($metadata['secure_token'])) {
            $metadata['secure_token'] = Str::random(40);
            $voucher->metadata = $metadata;
            $voucher->save();
        }
        $secureToken = $metadata['secure_token'];

        // 2. Fetch Store Details dynamically
        $shopName = $shop->shopify_domain;
        $shopLogoUrl = null;

        try {
            $response = $shopifyService->api($shop, 'GET', 'shop.json');
            if ($response->successful()) {
                $shopData = $response->json('shop');
                $shopName = $shopData['name'] ?? $shop->shopify_domain;
            }
        } catch (\Throwable $e) {
            Log::error("SendGiftCardEmailJob: Failed to fetch shop details via Shopify API: " . $e->getMessage());
        }

        // 3. Generate PDF
        $pdfData = null;
        try {
            $pdfData = $pdfService->generate($voucher, $shopName);
        } catch (\Throwable $e) {
            Log::error("SendGiftCardEmailJob: PDF Generation failed for voucher ID {$this->voucherId}: " . $e->getMessage());
            throw $e; // Throw exception to trigger queue retry
        }

        // 4. Send Emails based on Purchaser vs Recipient Logic
        $recipientEmail = trim(strtolower($voucher->recipient_email));
        $purchaserEmail = trim(strtolower($giftCardOrder->customer_email));

        try {
            if ($recipientEmail === $purchaserEmail) {
                // Send 1 email to the Recipient (which is also the purchaser)
                Mail::to($recipientEmail)->send(new RecipientGiftCardMail(
                    $voucher,
                    $shopName,
                    $shopLogoUrl,
                    $secureToken,
                    $pdfData
                ));
            } else {
                // Send Recipient email with PDF and Open button
                Mail::to($recipientEmail)->send(new RecipientGiftCardMail(
                    $voucher,
                    $shopName,
                    $shopLogoUrl,
                    $secureToken,
                    $pdfData
                ));

                // Send Purchaser confirmation email
                if (!empty($purchaserEmail)) {
                    Mail::to($purchaserEmail)->send(new PurchaserConfirmationMail(
                        $voucher,
                        $shopName,
                        $shopLogoUrl
                    ));
                }
            }

            // 5. Update Database on Success
            $voucher->sent_at = now();
            $voucher->status = 'delivered'; // Update status to reflect delivery
            $voucher->save();

            // Update Gift Card Order status to completed
            $giftCardOrder->status = 'completed';
            $giftCardOrder->save();

            Log::info("SendGiftCardEmailJob: Successfully sent Gift Card email(s) for Voucher ID {$this->voucherId}.");

        } catch (\Throwable $e) {
            Log::error("SendGiftCardEmailJob: Email sending failed for voucher ID {$this->voucherId}: " . $e->getMessage());
            throw $e; // Retry on failure
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendGiftCardEmailJob failed permanently for Voucher ID {$this->voucherId}: " . $exception->getMessage());
    }
}
