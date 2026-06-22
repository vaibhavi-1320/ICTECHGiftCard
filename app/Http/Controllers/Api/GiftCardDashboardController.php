<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTransaction;
use App\Models\GiftCardVoucher;
use Illuminate\Http\JsonResponse;

class GiftCardDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'total' => GiftCardVoucher::count(),
            'totalSold' => (float) GiftCardVoucher::where('status', '!=', 'pending_issuance')->sum('original_amount'),
            'totalRedeemed' => (float) GiftCardTransaction::sum('amount_used'),
            'expired' => GiftCardVoucher::whereDate('expires_at', '<', now())->whereNotIn('status', ['used', 'revoked'])->count(),
            'pending' => GiftCardVoucher::where('status', 'pending_issuance')->count(),
        ]);
    }
}
