<?php

namespace App\Mail;

use App\Models\GiftCardVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaserConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public GiftCardVoucher $voucher,
        public string $shopName,
        public ?string $shopLogoUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Gift Card has been successfully sent!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchaser_confirmation',
            with: [
                'recipientName'   => $this->voucher->recipient_name,
                'recipientEmail'  => $this->voucher->recipient_email,
                'amount'          => '$' . number_format((float) $this->voucher->original_amount, 2),
                'personalMessage' => $this->voucher->personal_message,
                'shopName'        => $this->shopName,
                'shopLogoUrl'     => $this->shopLogoUrl,
            ]
        );
    }
}
