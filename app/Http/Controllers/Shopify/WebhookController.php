<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessShopifyOrderJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function ordersCreated(Request $request): JsonResponse
    {
        ProcessShopifyOrderJob::dispatch($request->all(), $request->header('X-Shopify-Shop-Domain'));

        return response()->json(['ok' => true]);
    }

    public function ordersPaid(Request $request): JsonResponse
    {
        ProcessShopifyOrderJob::dispatch($request->all(), $request->header('X-Shopify-Shop-Domain'));

        return response()->json(['ok' => true]);
    }
}
