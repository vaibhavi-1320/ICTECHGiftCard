<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        if (empty($hmac)) {
            return response()->json(['message' => 'Missing signature header.'], 401);
        }

        $data = $request->getContent();
        $calculated = base64_encode(hash_hmac('sha256', $data, (string) config('shopify.api_secret'), true));

        if (!hash_equals($calculated, $hmac)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
