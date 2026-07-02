<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Models\Shop;
use App\Models\GiftCardOrder;
use App\Models\GiftCardVoucher;
use App\Models\GiftCardTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AppController extends Controller
{
    public function __invoke(Request $request)
    {
        $shopDomain = $request->string('shop')->toString();

        if (!$request->ajax() && !$request->wantsJson() && $request->routeIs('shopify.app') && $request->query('section') === null && !$request->has('p_status') && !$request->has('p_from') && !$request->has('p_search')) {
            return redirect()->route('shopify.gift-cards.index', $request->query());
        }

        $shop = null;
        if ($shopDomain !== '' && Schema::hasTable('shops')) {
            $shop = Shop::query()->where('shopify_domain', $shopDomain)->first();
        }

        $vouchersTable = Schema::hasTable('gift_card_vouchers');
        $transactionsTable = Schema::hasTable('gift_card_transactions');
        $ordersTable = Schema::hasTable('gift_card_orders');

        $stats = [
            'totalVouchers' => $vouchersTable ? GiftCardVoucher::count() : 0,
            'redeemedAmount' => $transactionsTable ? (float) GiftCardTransaction::sum('amount_used') : 0.0,
            'expiredVouchers' => $vouchersTable
                ? GiftCardVoucher::whereDate('expires_at', '<', now())->whereNotIn('status', ['used', 'revoked'])->count()
                : 0,
            'totalSold' => $vouchersTable ? (float) GiftCardVoucher::where('status', '!=', 'pending_issuance')->sum('original_amount') : 0.0,
            'usedVouchersCount' => $vouchersTable ? GiftCardVoucher::where('status', 'used')->count() : 0,
            'partiallyUsedVouchersCount' => $vouchersTable ? GiftCardVoucher::where('status', 'partially_used')->count() : 0,
        ];

        // Query Gift Card Orders (Step 5)
        $ordersQuery = GiftCardOrder::query()->with('vouchers');

        if ($request->filled('p_search')) {
            $search = $request->query('p_search');
            $ordersQuery->where(function ($q) use ($search) {
                $q->where('shopify_order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_email', 'like', "%{$search}%")
                  ->orWhere('template_name', 'like', "%{$search}%")
                  ->orWhereHas('vouchers', function ($vq) use ($search) {
                      $vq->where('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('p_status')) {
            $status = $request->query('p_status');
            $ordersQuery->where(function ($q) use ($status) {
                $q->where('status', $status)
                  ->orWhereHas('vouchers', function ($vq) use ($status) {
                      $vq->where('status', $status);
                  });
            });
        }

        if ($request->filled('p_from')) {
            $ordersQuery->whereDate('created_at', '>=', $request->query('p_from'));
        }

        if ($request->filled('p_to')) {
            $ordersQuery->whereDate('created_at', '<=', $request->query('p_to'));
        }

        $orders = $ordersTable ? $ordersQuery->latest()->paginate(10, ['*'], 'p_page')->withQueryString() : collect();

        $purchasedRows = collect();
        if ($ordersTable) {
            foreach ($orders as $order) {
                $voucher = $order->vouchers->first();
                $purchasedRows->push([
                    'id' => $order->id,
                    'shopifyOrderNumber' => $order->shopify_order_number ?: ('#' . $order->shopify_order_id),
                    'customerName' => $order->customer_name,
                    'recipientName' => $order->recipient_name,
                    'recipientEmail' => $order->recipient_email,
                    'amount' => $order->amount,
                    'remainingBalance' => $voucher ? (float) $voucher->remaining_balance : 0.0,
                    'templateName' => $order->template_name ?: 'Default',
                    'deliveryDate' => $order->delivery_date?->format('Y-m-d') ?: '',
                    'voucherCode' => $voucher ? $voucher->code : 'N/A',
                    'voucherStatus' => $voucher ? $voucher->status : 'pending',
                    'createdAt' => $order->created_at?->format('Y-m-d H:i'),
                ]);
            }
        }

        // Query used transactions
        $usedQuery = GiftCardTransaction::query()->with('voucher');
        if ($request->filled('u_from')) {
            $usedQuery->whereDate('created_at', '>=', $request->query('u_from'));
        }
        if ($request->filled('u_to')) {
            $usedQuery->whereDate('created_at', '<=', $request->query('u_to'));
        }
        $usedTransactions = $transactionsTable ? $usedQuery->latest()->paginate(10, ['*'], 'u_page')->withQueryString() : collect();

        $usedRows = $usedTransactions->map(fn ($t) => [
            'id' => $t->id,
            'code' => $t->voucher?->code,
            'customerName' => $t->customer_name ?: 'N/A',
            'customerEmail' => $t->customer_email,
            'amountUsed' => $t->amount_used,
            'balanceBefore' => $t->balance_before,
            'balanceAfter' => $t->balance_after,
            'orderId' => $t->shopify_order_id,
            'createdAt' => $t->created_at?->format('Y-m-d H:i'),
        ])->values();

        $purchasedPagination = null;
        if ($orders instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $purchasedPagination = [
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'hasPrevious' => !$orders->onFirstPage(),
                'hasNext' => $orders->hasMorePages(),
                'prevPageUrl' => $orders->previousPageUrl(),
                'nextPageUrl' => $orders->nextPageUrl(),
            ];
        }

        $usedPagination = null;
        if ($usedTransactions instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $usedPagination = [
                'currentPage' => $usedTransactions->currentPage(),
                'lastPage' => $usedTransactions->lastPage(),
                'hasPrevious' => !$usedTransactions->onFirstPage(),
                'hasNext' => $usedTransactions->hasMorePages(),
                'prevPageUrl' => $usedTransactions->previousPageUrl(),
                'nextPageUrl' => $usedTransactions->nextPageUrl(),
            ];
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'stats' => $stats,
                'purchasedRows' => $purchasedRows,
                'purchasedPagination' => $purchasedPagination ?? [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'hasPrevious' => false,
                    'hasNext' => false,
                    'prevPageUrl' => null,
                    'nextPageUrl' => null,
                ],
                'usedRows' => $usedRows,
                'usedPagination' => $usedPagination ?? [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'hasPrevious' => false,
                    'hasNext' => false,
                    'prevPageUrl' => null,
                    'nextPageUrl' => null,
                ],
            ]);
        }

        return view('admin', [
            'shop' => $shop,
            'shopDomain' => $shopDomain,
            'appApiKey' => config('shopify.api_key'),
            'embeddedAppUrl' => route('shopify.app', ['shop' => $shopDomain]),
            'stats' => $stats,
            'orders' => $orders,
            'purchasedRows' => $purchasedRows,
            'usedTransactions' => $usedTransactions,
            'usedRows' => $usedRows,
        ]);
    }

    public function purchasedExport(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');
        $dateFrom = $request->query('dateFrom');
        $dateTo = $request->query('dateTo');

        $query = GiftCardOrder::query()->with('vouchers');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('shopify_order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_email', 'like', "%{$search}%")
                  ->orWhere('template_name', 'like', "%{$search}%")
                  ->orWhereHas('vouchers', function ($vq) use ($search) {
                      $vq->where('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $query->where(function ($q) use ($status) {
                $q->where('status', $status)
                  ->orWhereHas('vouchers', function ($vq) use ($status) {
                      $vq->where('status', $status);
                  });
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gift-card-orders.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Shopify Order Number', 'Voucher Code', 'Customer Name', 'Customer Email', 
                'Recipient Name', 'Recipient Email', 'Sender Name', 'Amount', 
                'Template Name', 'Delivery Date', 'Voucher Status', 'Created At'
            ]);

            foreach ($orders as $order) {
                $voucher = $order->vouchers->first();
                fputcsv($file, [
                    $order->shopify_order_number ?: ('#' . $order->shopify_order_id),
                    $voucher ? $voucher->code : 'N/A',
                    $order->customer_name,
                    $order->customer_email,
                    $order->recipient_name,
                    $order->recipient_email,
                    $order->sender_name,
                    $order->amount,
                    $order->template_name ?: 'Default',
                    $order->delivery_date?->format('Y-m-d') ?: '',
                    $voucher ? $voucher->status : 'pending',
                    $order->created_at?->format('Y-m-d H:i:s') ?: '',
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
