<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifySession
{
    public function __construct(protected ShopifyService $shopifyService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $shopDomain = $request->query('shop') ?? $request->input('shop') ?? session('shopify_shop');

        if (!$shopDomain) {
            return response('Missing shop parameter.', 400);
        }

        $shopDomain = $this->shopifyService->sanitizeShopDomain($shopDomain);

        // Ensure shop is installed and token exists in the database
        $shop = Shop::query()
            ->where('shopify_domain', $shopDomain)
            ->whereNotNull('access_token')
            ->first();

        $host = $request->query('host') ?? session('shopify_host');
        if ($request->has('host')) {
            session(['shopify_host' => $request->query('host')]);
        }

        if (!$shop) {
            $redirectParams = $request->query();
            $redirectParams['shop'] = $shopDomain;
            if ($host && !isset($redirectParams['host'])) {
                $redirectParams['host'] = $host;
            }
            return redirect()->route('shopify.install', $redirectParams);
        }

        $sessionShop = session('shopify_shop');
        $isSessionValid = $sessionShop && strtolower($sessionShop) === strtolower($shopDomain);

        if ($request->has('hmac')) {
            // Only verify HMAC if we don't already have a valid session for this shop.
            // Stale hmac params from Shopify Admin URL persist across internal navigation
            // and will fail verification since they were computed for a different URL.
            if (!$isSessionValid) {
                if ($this->shopifyService->verifyCallback($request->query())) {
                    session(['shopify_shop' => $shopDomain]);
                    $isSessionValid = true;
                } else {
                    return response('Invalid HMAC signature.', 403);
                }
            }
        } else {
            if (!$isSessionValid) {
                $redirectParams = $request->query();
                $redirectParams['shop'] = $shopDomain;
                if ($host && !isset($redirectParams['host'])) {
                    $redirectParams['host'] = $host;
                }
                return redirect()->route('shopify.install', $redirectParams);
            }
        }

        // Verify if the token is still valid and has correct permissions
        if ($request->has('hmac') || !session('shopify_token_verified')) {
            try {
                $apiRes = $this->shopifyService->api($shop, 'GET', 'admin/oauth/access_scopes.json');
                if (!$apiRes->successful()) {
                    $shop->update(['access_token' => null]);
                    session()->forget(['shopify_shop', 'shopify_token_verified']);
                    $redirectParams = $request->query();
                    $redirectParams['shop'] = $shopDomain;
                    if ($host && !isset($redirectParams['host'])) {
                        $redirectParams['host'] = $host;
                    }
                    return redirect()->route('shopify.install', $redirectParams);
                }

                $scopesData = $apiRes->json();
                $grantedScopes = collect($scopesData['access_scopes'] ?? [])->pluck('handle')->toArray();
                $requiredScopes = config('shopify.scopes');

                $missingScopes = array_diff($requiredScopes, $grantedScopes);
                if (!empty($missingScopes)) {
                    $shop->update(['access_token' => null]);
                    session()->forget(['shopify_shop', 'shopify_token_verified']);
                    $redirectParams = $request->query();
                    $redirectParams['shop'] = $shopDomain;
                    if ($host && !isset($redirectParams['host'])) {
                        $redirectParams['host'] = $host;
                    }
                    return redirect()->route('shopify.install', $redirectParams);
                }

                session(['shopify_token_verified' => true]);
            } catch (\Throwable $e) {
                $shop->update(['access_token' => null]);
                session()->forget(['shopify_shop', 'shopify_token_verified']);
                $redirectParams = $request->query();
                $redirectParams['shop'] = $shopDomain;
                if ($host && !isset($redirectParams['host'])) {
                    $redirectParams['host'] = $host;
                }
                return redirect()->route('shopify.install', $redirectParams);
            }
        }

        return $next($request);
    }
}
