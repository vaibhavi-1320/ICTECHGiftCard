<?php

namespace App\Http\Controllers;

use App\Models\GiftCardTransaction;
use App\Models\GiftCardVoucher;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminShellController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'totalVouchers' => Schema::hasTable('gift_card_vouchers') ? GiftCardVoucher::count() : 0,
            'pendingVouchers' => Schema::hasTable('gift_card_vouchers') ? GiftCardVoucher::where('status', 'pending_issuance')->count() : 0,
            'redeemedAmount' => Schema::hasTable('gift_card_transactions') ? (float) GiftCardTransaction::sum('amount_used') : 0.0,
            'expiredVouchers' => Schema::hasTable('gift_card_vouchers')
                ? GiftCardVoucher::whereDate('expires_at', '<', now())->whereNotIn('status', ['used', 'revoked'])->count()
                : 0,
            'totalSold' => Schema::hasTable('gift_card_vouchers') ? (float) GiftCardVoucher::where('status', '!=', 'pending_issuance')->sum('original_amount') : 0.0,
        ];

        return view('admin', [
            'shop' => null,
            'shopDomain' => '',
            'appApiKey' => config('shopify.api_key'),
            'embeddedAppUrl' => route('shopify.app'),
            'stats' => $stats,
        ]);
    }
}
