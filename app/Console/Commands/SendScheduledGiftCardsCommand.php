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

        // $today = '2026-07-01';
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
                \App\Jobs\SendGiftCardEmailJob::dispatch($voucher->id);
                $this->info("Dispatched email sending job for gift card {$voucher->code} to {$voucher->recipient_email}");
            } catch (\Throwable $e) {
                Log::error("SendScheduledGiftCardsCommand: Failed dispatching job for voucher {$voucher->id}: " . $e->getMessage());
                $this->error("Failed dispatching job for voucher {$voucher->id}: " . $e->getMessage());
            }
        }
    }
}
