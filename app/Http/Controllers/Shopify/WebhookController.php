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

    public function customersDataRequest(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function customersRedact(Request $request): JsonResponse
    {
        $payload = $request->all();
        $customerId = $payload['customer']['id'] ?? null;

        if ($customerId) {
            \App\Models\GiftCardVoucher::where('shopify_customer_id', $customerId)
                ->update([
                    'recipient_email' => 'redacted@example.com',
                    'recipient_name' => 'Redacted',
                    'sender_name' => 'Redacted',
                    'personal_message' => null
                ]);
        }

        return response()->json(['success' => true]);
    }

    public function shopRedact(Request $request): JsonResponse
    {
        $payload = $request->all();
        $shopDomain = $payload['shop_domain'] ?? null;

        if ($shopDomain) {
            $shop = \App\Models\Shop::where('shopify_domain', $shopDomain)->first();
            if ($shop) {
                $this->purgeShopData($shop);
            }
        }

        return response()->json(['success' => true]);
    }

    public function appUninstalled(Request $request): JsonResponse
    {
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        if ($shopDomain) {
            $shop = \App\Models\Shop::where('shopify_domain', $shopDomain)->first();
            if ($shop) {
                $this->purgeShopData($shop);
            }
        }

        return response()->json(['success' => true]);
    }

    private function purgeShopData(\App\Models\Shop $shop): void
    {
        // Get all gift cards of this shop
        $giftCardIds = \App\Models\GiftCard::where('shop_id', $shop->id)->pluck('id');

        // Get all vouchers of those gift cards
        $vouchers = \App\Models\GiftCardVoucher::whereIn('gift_card_id', $giftCardIds)->get();
        $voucherIds = $vouchers->pluck('id');
        $orderIds = $vouchers->pluck('gift_card_order_id')->filter()->unique();

        // 1. Delete audit logs associated with those vouchers
        \App\Models\GiftCardAuditLog::whereIn('voucher_id', $voucherIds)->delete();

        // 2. Delete transactions associated with those vouchers
        \App\Models\GiftCardTransaction::whereIn('voucher_id', $voucherIds)->delete();

        // 3. Delete vouchers
        \App\Models\GiftCardVoucher::whereIn('id', $voucherIds)->delete();

        // 4. Delete gift card orders
        \App\Models\GiftCardOrder::whereIn('id', $orderIds)->delete();

        // 5. Delete gift cards
        \App\Models\GiftCard::where('shop_id', $shop->id)->delete();

        // 6. Delete templates of this shop
        \App\Models\GiftCardTemplate::where('shop_id', $shop->id)->delete();

        // 6.5. Remove page and navigation link from Shopify storefront
        try {
            $shopifyService = resolve(\App\Services\Shopify\ShopifyService::class);
            $shopifyService->removeStorefrontResources($shop);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to remove Shopify storefront resources during purge: ' . $e->getMessage());
        }

        // 7. Finally delete the shop record itself
        $shop->delete();
    }

    public function compliance(Request $request): JsonResponse
    {
        $topic = $request->header('X-Shopify-Topic');
        
        switch ($topic) {
            case 'customers/redact':
                return $this->customersRedact($request);
            case 'customers/data_request':
                return $this->customersDataRequest($request);
            case 'shop/redact':
                return $this->shopRedact($request);
            default:
                return response()->json(['success' => true]);
        }
    }
}
