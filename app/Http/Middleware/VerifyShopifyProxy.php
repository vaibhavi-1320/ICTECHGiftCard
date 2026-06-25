<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $params = $request->query();
        $signature = $params['signature'] ?? '';

        if (empty($signature)) {
            if (app()->environment('local')) {
                return $next($request);
            }
            return response('Missing app proxy signature.', 401);
        }

        unset($params['signature']);
        ksort($params);

        $data = '';
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $data .= "{$key}={$value}";
        }

        $calculated = hash_hmac('sha256', $data, (string) config('shopify.api_secret'));

        if (!hash_equals($calculated, $signature)) {
            return response('Invalid app proxy signature.', 401);
        }

        return $next($request);
    }
}
