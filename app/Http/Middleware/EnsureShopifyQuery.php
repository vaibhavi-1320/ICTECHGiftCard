<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopifyQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('testing') && $request->is('shopify/app') && $request->query('shop') === null) {
            $request->query->set('shop', (string) config('shopify.host_name'));
        }

        return $next($request);
    }
}
