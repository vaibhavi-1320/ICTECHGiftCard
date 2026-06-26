<?php

namespace App\Mail;

use App\Models\GiftCardVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GiftCardMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GiftCardVoucher $voucher)
    {
    }

    public function envelope(): Envelope
    {
        $senderName = $this->voucher->sender_name ?: 'A Friend';
        
        $shop = $this->voucher->giftCard?->shop;
        $subjectFormat = $shop 
            ? $shop->getSetting('emailSubjectRecipient') 
            : 'Gift card offer from %s';

        $subject = sprintf($subjectFormat ?: 'Gift card offer from %s', $senderName);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $giftCard = $this->voucher->giftCard;
        $template = $giftCard?->template;
        $shop     = $giftCard?->shop;
        $metadata = $this->voucher->metadata ?? [];

        $html = $template?->body_html ?: ($shop ? $shop->getSetting('pdfContent') : '');

        if (empty($html)) {
            $html = '<p>Hi {{card_lastname}},</p>'
                  . '<p>You have received a <strong>{{card_price}}</strong> Gift Card from <strong>{{card_from}}</strong>!</p>'
                  . '{{card_image}}'
                  . '<p><strong>Your Code: {{card_code}}</strong></p>'
                  . '<p>Valid until: {{validity_date}}</p>'
                  . '<p>{{card_message}}</p>';
        }

        // Resolve template image: prefer relationship, fall back to metadata URL
        $templateImagePath = $metadata['template_image_url'] ?? null;
        $templateMediaUrl  = null;

        if ($template?->media_url) {
            $templateMediaUrl = Storage::disk('public')->url($template->media_url);
        } elseif ($templateImagePath) {
            // Could be a relative path like gift-card-templates/xxx.png
            if (filter_var($templateImagePath, FILTER_VALIDATE_URL)) {
                $templateMediaUrl = $templateImagePath;
            } else {
                $templateMediaUrl = Storage::disk('public')->url($templateImagePath);
            }
        }

        $imageHtml = $templateMediaUrl
            ? '<img src="' . $templateMediaUrl . '" style="max-width:400px;width:100%;height:auto;border-radius:8px;display:block;margin:16px auto;" />'
            : '';

        $replacements = [
            '{{card_lastname}}'  => $this->voucher->recipient_name,
            '{{card_firstname}}' => '',
            '{{card_price}}'     => '$' . number_format((float) $this->voucher->original_amount, 2),
            '{{card_from}}'      => $this->voucher->sender_name ?: 'A Friend',
            '{{card_code}}'      => $this->voucher->code,
            '{{card_message}}'   => nl2br(htmlspecialchars($this->voucher->personal_message ?: '')),
            '{{card_image}}'     => $imageHtml,
            '{{shop_name}}'      => $shop ? $shop->shopify_domain : 'My Store',
            '{{validity_date}}'  => $this->voucher->expires_at?->format('d.m.Y') ?? '',
        ];

        if ($template) {
            $metadata = $template->metadata ?? [];
            $replacements['{{custom_text_1}}'] = $metadata['custom_text_1'] ?? '';
            $replacements['{{custom_text_2}}'] = $metadata['custom_text_2'] ?? '';
            $replacements['{{custom_text_3}}'] = $metadata['custom_text_3'] ?? '';
        }

        $body = str_replace(array_keys($replacements), array_values($replacements), $html);

        return new Content(
            htmlString: $body,
        );
    }
}
