<?php

namespace App\Mail;

use App\Models\GiftCardVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RecipientGiftCardMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public GiftCardVoucher $voucher,
        public string $shopName,
        public ?string $shopLogoUrl,
        public string $secureToken,
        public ?string $pdfData = null
    ) {
    }

    public function envelope(): Envelope
    {
        $senderName = $this->voucher->sender_name ?: 'A Friend';
        $subject = sprintf('A special gift card for you from %s!', $senderName);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $giftCard = $this->voucher->giftCard;
        $template = $giftCard?->template;
        
        // Resolve template image: prefer relationship, fall back to metadata URL
        $templateImagePath = $this->voucher->metadata['template_image_url'] ?? null;
        $templateMediaUrl  = null;
        $relativePath      = null;

        if ($template?->media_url) {
            $relativePath = $template->media_url;
            $templateMediaUrl = Storage::disk('public')->url($template->media_url);
        } elseif ($templateImagePath) {
            if (filter_var($templateImagePath, FILTER_VALIDATE_URL)) {
                $templateMediaUrl = $templateImagePath;
            } else {
                $relativePath = $templateImagePath;
                $templateMediaUrl = Storage::disk('public')->url($templateImagePath);
            }
        }

        // Bypassing ngrok warning on local environment by hosting image via tmpfiles.org
        if ($relativePath && (app()->environment('local') || str_contains($templateMediaUrl, 'ngrok'))) {
            try {
                $filePath = Storage::disk('public')->path($relativePath);
                if (file_exists($filePath)) {
                    $response = \Illuminate\Support\Facades\Http::attach(
                        'file', file_get_contents($filePath), basename($filePath)
                    )->post('https://tmpfiles.org/api/v1/upload');

                    if ($response->successful()) {
                        $data = $response->json();
                        $tempUrl = $data['data']['url'] ?? null;
                        if ($tempUrl) {
                            $templateMediaUrl = str_replace('tmpfiles.org/', 'tmpfiles.org/dl/', $tempUrl);
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('RecipientGiftCardMail: Failed to upload template image to tmpfiles: ' . $e->getMessage());
            }
        }

        $openUrl = url('/gift-card/open/' . $this->secureToken);

        return new Content(
            view: 'emails.recipient_gift_card',
            with: [
                'templateMediaUrl' => $templateMediaUrl,
                'openUrl'          => $openUrl,
                'senderName'       => $this->voucher->sender_name ?: 'A Friend',
                'recipientName'    => $this->voucher->recipient_name,
                'amount'           => '$' . number_format((float) $this->voucher->remaining_balance, 2),
                'personalMessage'  => $this->voucher->personal_message,
                'code'             => $this->voucher->code,
                'expiryDate'       => $this->voucher->expires_at?->format('d.m.Y'),
                'shopName'         => $this->shopName,
                'shopLogoUrl'      => $this->shopLogoUrl,
            ]
        );
    }

    public function attachments(): array
    {
        if ($this->pdfData) {
            return [
                Attachment::fromData(fn () => $this->pdfData, 'GiftCard-' . $this->voucher->code . '.pdf')
                    ->withMime('application/pdf'),
            ];
        }
        return [];
    }
}
