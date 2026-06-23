<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\GiftCardVoucher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StorefrontController extends Controller
{
    public function index(Request $request): Response
    {
        $customerId = $request->query('logged_in_customer_id');

        $vouchers = collect();
        if ($customerId) {
            $vouchers = GiftCardVoucher::where('shopify_customer_id', $customerId)
                ->latest()
                ->get();
        }

        $activeGiftCards = \App\Models\GiftCard::where('active', true)
            ->latest()
            ->get();

        $content = view('shopify.storefront.customer-vouchers', [
            'vouchers' => $vouchers,
            'activeGiftCards' => $activeGiftCards,
            'isLoggedIn' => !empty($customerId),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'text/html');
    }
}
