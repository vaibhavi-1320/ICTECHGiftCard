<?php

namespace App\Jobs;

use App\Models\GiftCardVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGiftCardEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $voucherId)
    {
    }

    public function handle(): void
    {
        $voucher = GiftCardVoucher::find($this->voucherId);
        if ($voucher === null || $voucher->recipient_email === '') {
            return;
        }

        $voucher->forceFill(['sent_at' => now(), 'status' => 'sent'])->save();
    }
}
