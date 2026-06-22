<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - ICTECH Gift Card</title>
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}" />
    <link rel="stylesheet" href="https://unpkg.com/@shopify/polaris@12.0.0/build/esm/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    @vite(['resources/js/app.js'])
    <style>
        body { 
            margin: 0; 
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; 
            background: var(--p-color-bg-app, #f6f6f7); 
            color: var(--p-color-text, #202223); 
            -webkit-font-smoothing: antialiased;
        }
        .polaris-form-inline {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
    </style>
</head>
<body>
    @php
        $appShopDomain = $shopDomain ?? request('shop', '');
        $entryUrl = $embeddedAppUrl ?? route('shopify.app', ['shop' => $appShopDomain]);
        $stats = array_merge(['totalVouchers' => 0, 'pendingVouchers' => 0, 'redeemedAmount' => 0, 'expiredVouchers' => 0, 'totalSold' => 0.0], $stats ?? []);
        $purchasedVouchers = $purchasedVouchers ?? collect();
        $usedTransactions = $usedTransactions ?? collect();
        $purchasedRows = $purchasedVouchers->map(fn ($v) => [
            'id' => $v->id,
            'code' => $v->code,
            'originalAmount' => $v->original_amount,
            'remainingBalance' => $v->remaining_balance,
            'status' => $v->status,
            'recipientName' => $v->recipient_name,
            'orderId' => $v->shopify_order_id,
            'createdAt' => $v->created_at?->format('Y-m-d'),
        ])->values();
        $usedRows = $usedTransactions->map(fn ($t) => [
            'id' => $t->id,
            'code' => $t->voucher?->code,
            'amountUsed' => $t->amount_used,
            'balanceBefore' => $t->balance_before,
            'balanceAfter' => $t->balance_after,
            'orderId' => $t->shopify_order_id,
            'createdAt' => $t->created_at?->format('Y-m-d H:i'),
        ])->values();
    @endphp

    <ui-nav-menu>
        <a href="{{ route('shopify.dashboard', request()->query(), false) }}" rel="home">ICTECHGiftCard</a>
        <a href="{{ route('shopify.dashboard', request()->query(), false) }}">Dashboard</a>
        <a href="{{ route('shopify.gift-cards.index', request()->query(), false) }}">Gift Cards</a>
        <a href="{{ route('shopify.templates.index', request()->query(), false) }}">Templates</a>
        <a href="{{ route('shopify.settings.edit', request()->query(), false) }}">Settings</a>
    </ui-nav-menu>

    <ui-title-bar title="Dashboard"></ui-title-bar>

    <div class="Polaris-Page">
        <div class="Polaris-Page__Content">
            <div id="dashboard-overview-react" data-stats='@json($stats)' style="margin-bottom: 16px;"></div>
            <div id="dashboard-purchased-react" data-config="{{ e(json_encode([
                            "title" => "Gift Cards Purchased",
                            "exportUrl" => route("shopify.dashboard.purchased-export", array_merge(request()->query(), ["status" => request("p_status"), "dateFrom" => request("p_from"), "dateTo" => request("p_to")]), false),
                            "resetUrl" => route("shopify.app", \Illuminate\Support\Arr::except(request()->query(), ["p_status", "p_from", "p_to", "p_page", "u_page"]), false),
                            "filters" => [
                                "status" => request("p_status", ""),
                                "from" => request("p_from", ""),
                                "to" => request("p_to", ""),
                            ],
                            "rows" => $purchasedRows,
                        ])) }}"></div>
            <div id="dashboard-used-react" data-config="{{ e(json_encode([
                            "title" => "Gift Cards Used",
                            "exportUrl" => route("shopify.dashboard.used-export", array_merge(request()->query(), ["dateFrom" => request("u_from"), "dateTo" => request("u_to")]), false),
                            "resetUrl" => route("shopify.app", \Illuminate\Support\Arr::except(request()->query(), ["u_from", "u_to", "p_page", "u_page"]), false),
                            "filters" => [
                                "from" => request("u_from", ""),
                                "to" => request("u_to", ""),
                            ],
                            "rows" => $usedRows,
                        ])) }}"></div>
            </div>
        </div>
    </div>
    
    <script>
        (function () {
            const search = new URLSearchParams(window.location.search);
            const shop = search.get('shop') || @json($appShopDomain);
            const apiKey = @json($appApiKey ?? config('shopify.api_key'));
            const host = search.get('host');

            if (window.shopifyAppBridge && apiKey && host) {
                const app = window.shopifyAppBridge.default({
                    apiKey,
                    host,
                    forceRedirect: true,
                });
                const redirect = window.shopifyAppBridge.actions.Redirect.create(app);
                if (!search.get('shop')) {
                    redirect.dispatch(window.shopifyAppBridge.actions.Redirect.Action.APP, '/shopify/app?shop=' + encodeURIComponent(shop));
                }
            }
        })();
    </script>
</body>
</html>
