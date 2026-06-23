<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardTemplate;
use App\Models\GiftCardVoucher;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StorefrontController extends Controller
{
    public function index(Request $request): Response
    {
        $customerId = $request->query('logged_in_customer_id');

        $vouchers = collect();
        if ($customerId) {
            $vouchers = GiftCardVoucher::where('shopify_customer_id', $customerId)
                ->latest()
                ->get();
        }

        $shop = null;
        if ($shopDomain = $request->query('shop')) {
            $shop = Shop::query()->where('shopify_domain', $shopDomain)->first();
        }

        $templates = GiftCardTemplate::query()
            ->when($shop?->id, fn ($query, $shopId) => $query->where('shop_id', $shopId))
            ->where('active', true)
            ->with(['giftCards' => fn ($query) => $query->where('active', true)->latest()])
            ->latest()
            ->get()
            ->map(function (GiftCardTemplate $template) {
                $giftCard = $template->giftCards->first();

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'tag' => $template->tag,
                    'imageUrl' => $template->media_url ? Storage::disk('public')->url($template->media_url) : null,
                    'giftCardName' => $giftCard?->name,
                    'giftCardAmount' => $giftCard?->amount,
                    'variantId' => $giftCard?->shopify_product_variant_id,
                    'productId' => $giftCard?->shopify_product_id,
                ];
            })
            ->values();

        $tags = $templates
            ->pluck('tag')
            ->filter()
            ->unique()
            ->values();

        $content = view('shopify.storefront.customer-vouchers', [
            'vouchers' => $vouchers,
            'templates' => $templates,
            'tags' => $tags,
            'isLoggedIn' => !empty($customerId),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'text/html');
    }
}
