<?php

namespace App\Console\Commands;

use App\Models\GiftCardVoucher;
use App\Mail\GiftCardMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendScheduledGiftCardsCommand extends Command
{
    protected $signature = 'giftcard:send-emails';
    protected $description = 'Send scheduled gift card emails whose scheduled send date has arrived.';

    public function handle(): void
    {
        $today = now()->format('Y-m-d');

        $vouchers = GiftCardVoucher::where('status', 'unused')
            ->whereDate('scheduled_send_date', '<=', $today)
            ->whereNull('sent_at')
            ->whereNotNull('recipient_email')
            ->where('recipient_email', '!=', '')
            ->limit(100)
            ->get();

        $this->info("Found " . $vouchers->count() . " scheduled gift card email(s) to send.");

        foreach ($vouchers as $voucher) {
            try {
                Mail::to($voucher->recipient_email)->send(new GiftCardMail($voucher));
                $voucher->sent_at = now();
                $voucher->save();
                $this->info("Sent gift card {$voucher->code} to {$voucher->recipient_email}");
            } catch (\Throwable $e) {
                Log::error("SendScheduledGiftCardsCommand: Failed sending voucher {$voucher->id}: " . $e->getMessage());
                $this->error("Failed sending voucher {$voucher->id}: " . $e->getMessage());
            }
        }
    }
}
