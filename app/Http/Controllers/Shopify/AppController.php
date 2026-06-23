<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Models\Shop;
use App\Models\GiftCardVoucher;
use App\Models\GiftCardTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AppController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $shopDomain = $request->string('shop')->toString();

        if ($request->routeIs('shopify.app') && $request->query('section') === null && !$request->has('p_status') && !$request->has('p_from')) {
            return redirect()->route('shopify.gift-cards.index', $request->query());
        }

        $shop = null;
        if ($shopDomain !== '' && Schema::hasTable('shops')) {
            $shop = Shop::query()->where('shopify_domain', $shopDomain)->first();
        }

        $vouchersTable = Schema::hasTable('gift_card_vouchers');
        $transactionsTable = Schema::hasTable('gift_card_transactions');

        $stats = [
            'totalVouchers' => $vouchersTable ? GiftCardVoucher::count() : 0,
            'pendingVouchers' => $vouchersTable ? GiftCardVoucher::where('status', 'pending_issuance')->count() : 0,
            'redeemedAmount' => $transactionsTable ? (float) GiftCardTransaction::sum('amount_used') : 0.0,
            'expiredVouchers' => $vouchersTable
                ? GiftCardVoucher::whereDate('expires_at', '<', now())->whereNotIn('status', ['used', 'revoked'])->count()
                : 0,
            'totalSold' => $vouchersTable ? (float) GiftCardVoucher::where('status', '!=', 'pending_issuance')->sum('original_amount') : 0.0,
        ];

        // Query purchased vouchers
        $purchasedQuery = GiftCardVoucher::query();
        if ($request->filled('p_status')) {
            $purchasedQuery->where('status', $request->query('p_status'));
        }
        if ($request->filled('p_from')) {
            $purchasedQuery->whereDate('created_at', '>=', $request->query('p_from'));
        }
        if ($request->filled('p_to')) {
            $purchasedQuery->whereDate('created_at', '<=', $request->query('p_to'));
        }
        $purchasedVouchers = $vouchersTable ? $purchasedQuery->latest()->paginate(10, ['*'], 'p_page')->withQueryString() : collect();

        // Query used transactions
        $usedQuery = GiftCardTransaction::query()->with('voucher');
        if ($request->filled('u_from')) {
            $usedQuery->whereDate('created_at', '>=', $request->query('u_from'));
        }
        if ($request->filled('u_to')) {
            $usedQuery->whereDate('created_at', '<=', $request->query('u_to'));
        }
        $usedTransactions = $transactionsTable ? $usedQuery->latest()->paginate(10, ['*'], 'u_page')->withQueryString() : collect();

        return view('admin', [
            'shop' => $shop,
            'shopDomain' => $shopDomain,
            'appApiKey' => config('shopify.api_key'),
            'embeddedAppUrl' => route('shopify.app', ['shop' => $shopDomain]),
            'stats' => $stats,
            'purchasedVouchers' => $purchasedVouchers,
            'usedTransactions' => $usedTransactions,
        ]);
    }

    public function purchasedExport(Request $request)
    {
        $status = $request->query('status');
        $dateFrom = $request->query('dateFrom');
        $dateTo = $request->query('dateTo');

        $query = GiftCardVoucher::query();
        if ($status) {
            $query->where('status', $status);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $vouchers = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gift-cards-purchased.csv"',
        ];

        $callback = function () use ($vouchers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Code', 'Original Amount', 'Remaining Balance', 'Status', 'Recipient Name', 'Recipient Email', 'Sender Name', 'Expires At', 'Created At', 'Order ID']);

            foreach ($vouchers as $v) {
                fputcsv($file, [
                    $v->code,
                    $v->original_amount,
                    $v->remaining_balance,
                    $v->status,
                    $v->recipient_name,
                    $v->recipient_email,
                    $v->sender_name,
                    $v->expires_at?->format('Y-m-d') ?: '',
                    $v->created_at?->format('Y-m-d H:i:s') ?: '',
                    $v->shopify_order_id ?: ''
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function usedExport(Request $request)
    {
        $dateFrom = $request->query('dateFrom');
        $dateTo = $request->query('dateTo');

        $query = GiftCardTransaction::query()->with('voucher');
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $transactions = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gift-cards-used.csv"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Code', 'Amount Used', 'Balance Before', 'Balance After', 'Used At', 'Order ID', 'Customer ID']);

            foreach ($transactions as $t) {
                fputcsv($file, [
                    $t->voucher?->code ?: '',
                    $t->amount_used,
                    $t->balance_before,
                    $t->balance_after,
                    $t->created_at?->format('Y-m-d H:i:s') ?: '',
                    $t->shopify_order_id ?: '',
                    $t->shopify_customer_id ?: ''
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
