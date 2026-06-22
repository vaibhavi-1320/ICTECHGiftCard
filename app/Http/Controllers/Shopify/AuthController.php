<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuthController extends Controller
{
    public function install(Request $request, ShopifyService $service)
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
        ]);

        $shop = $service->sanitizeShopDomain($data['shop']);
        $installUrl = $service->installUrl($shop);

        return response("<!DOCTYPE html>
<html>
<head>
    <script>
        window.top.location.href = '" . $installUrl . "';
    </script>
</head>
<body>
    <p>Redirecting to install app...</p>
</body>
</html>");
    }

    public function callback(Request $request, ShopifyService $service)
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
            'code' => ['required', 'string'],
            'hmac' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $shopDomain = $service->sanitizeShopDomain($data['shop']);

        if (! $service->verifyCallback($request->query())) {
            return response()->json(['message' => 'Invalid OAuth HMAC.'], 403);
        }

        $sessionState = (string) session('shopify_oauth_state');
        if ($sessionState !== '' && $request->string('state')->toString() !== $sessionState) {
            return response()->json(['message' => 'Invalid OAuth state.'], 403);
        }

        $token = $service->exchangeToken($shopDomain, $data['code']);
        if ($token === null) {
            return response()->json(['message' => 'Unable to exchange token.'], 502);
        }

        $shop = Shop::query()->updateOrCreate(
            ['shopify_domain' => $shopDomain],
            [
                'access_token' => $token,
                'installed_at' => now(),
                'metadata' => ['last_callback_at' => now()->toIso8601String()],
            ]
        );

        try {
            $service->createStorefrontResources($shop);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Storefront resources setup failed: ' . $e->getMessage());
        }

        session()->forget('shopify_oauth_state');

        $apiKey = config('shopify.api_key');
        return response("<!DOCTYPE html>
<html>
<head>
    <script>
        window.top.location.href = 'https://{$shopDomain}/admin/apps/{$apiKey}';
    </script>
</head>
<body>
    <p>Redirecting to Shopify Admin...</p>
</body>
</html>");
    }
}
