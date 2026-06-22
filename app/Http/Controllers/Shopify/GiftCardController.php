<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardTemplate;
use App\Models\GiftCardVoucher;
use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GiftCardController extends Controller
{
    public function index(Request $request, ShopifyService $shopifyService): View|\Illuminate\Http\RedirectResponse
    {
        $shopDomain = $request->string('shop')->toString();
        $shop = $this->resolveShop($shopDomain);

        if ($shop) {
            try {
                $shopifyService->createStorefrontResources($shop);
            } catch (\Exception $e) {
                if ($e->getMessage() === 'Unauthorized') {
                    $shop->update(['access_token' => null]);
                    session()->forget(['shopify_shop', 'shopify_token_verified']);
                    $redirectParams = $request->query();
                    $redirectParams['shop'] = $shopDomain;
                    if ($request->has('host')) {
                        $redirectParams['host'] = $request->query('host');
                    }
                    return redirect()->route('shopify.install', $redirectParams);
                }
            }
        }

        $giftCards = Schema::hasTable('gift_cards')
            ? GiftCard::query()
                ->when($shop?->id, fn ($query, $shopId) => $query->where('shop_id', $shopId))
                ->latest()
                ->get()
            : collect();

        return view('shopify.gift-cards.index', [
            'shop' => $shop,
            'shopDomain' => $shopDomain,
            'giftCards' => $giftCards,
        ]);
    }

    public function create(Request $request): View
    {
        return view('shopify.gift-cards.form', $this->viewData($request, new GiftCard()));
    }

    public function store(Request $request, ShopifyService $shopifyService): RedirectResponse
    {
        $data = $this->validateRequest($request);
        $shop = $this->resolveShop($request->string('shop')->toString());

        if (!$shop) {
            return redirect()->back()->withErrors(['shop' => 'Shop not found.']);
        }

        $giftCard = new GiftCard($data);
        $giftCard->shop_id = $shop->id;
        if ($request->hasFile('image_upload')) {
            $giftCard->image_url = $request->file('image_upload')->store('gift-cards', 'public');
        }
        $giftCard->save();

        // Sync to Shopify
        $title = $giftCard->name;
        $amount = $giftCard->amount;
        $prefix = $giftCard->code_prefix;
        $imageUrl = $giftCard->image_url ? url('/storage/' . $giftCard->image_url) : null;

        $payload = [
            'product' => [
                'title' => $title,
                'body_html' => '<p>Gift Card value: ' . number_format($amount, 2) . '</p>',
                'vendor' => 'Gift Card App',
                'product_type' => 'Gift Card',
                'status' => $giftCard->active ? 'active' : 'draft',
                'variants' => [
                    [
                        'price' => $amount,
                        'sku' => ($prefix ?: 'GC') . '-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'inventory_policy' => 'deny',
                        'fulfillment_service' => 'manual',
                        'requires_shipping' => false,
                        'taxable' => false
                    ]
                ]
            ]
        ];

        if ($imageUrl) {
            $payload['product']['images'] = [
                ['src' => $imageUrl]
            ];
        }

        try {
            $response = $shopifyService->api($shop, 'POST', 'products.json', $payload);
            if ($response->successful()) {
                $resData = $response->json();
                $giftCard->shopify_product_id = $resData['product']['id'];
                $giftCard->shopify_product_variant_id = $resData['product']['variants'][0]['id'];
                $giftCard->save();
            }
        } catch (\Throwable $e) {
            // Log or handle error, but proceed to save the gift card locally
        }

        // Generate voucher pool
        $this->generateVoucherPool($giftCard);
 
        return redirect()->route('shopify.gift-cards.index', $request->query())
            ->with('status', 'Gift card created.');
    }
 
    public function edit(Request $request, GiftCard $giftCard): View
    {
        return view('shopify.gift-cards.form', $this->viewData($request, $giftCard));
    }
 
    public function update(Request $request, GiftCard $giftCard, ShopifyService $shopifyService): RedirectResponse
    {
        $giftCard->fill($this->validateRequest($request));
        if ($request->hasFile('image_upload')) {
            $giftCard->image_url = $request->file('image_upload')->store('gift-cards', 'public');
        }
        $giftCard->save();
 
        $shop = $this->resolveShop($request->string('shop')->toString());
 
        if ($shop && $giftCard->shopify_product_id) {
            $payload = [
                'product' => [
                    'id' => $giftCard->shopify_product_id,
                    'title' => $giftCard->name,
                    'status' => $giftCard->active ? 'active' : 'draft',
                ]
            ];
 
            $imageUrl = $giftCard->image_url ? url('/storage/' . $giftCard->image_url) : null;
            if ($imageUrl) {
                $payload['product']['images'] = [
                    ['src' => $imageUrl]
                ];
            }
 
            try {
                $shopifyService->api($shop, 'PUT', "products/{$giftCard->shopify_product_id}.json", $payload);
 
                if ($giftCard->shopify_product_variant_id) {
                    $varPayload = [
                        'variant' => [
                            'id' => $giftCard->shopify_product_variant_id,
                            'price' => $giftCard->amount,
                        ]
                    ];
                    $shopifyService->api($shop, 'PUT', "variants/{$giftCard->shopify_product_variant_id}.json", $varPayload);
                }
            } catch (\Throwable $e) {
                // Ignore errors during sync to prevent app crash
            }
        }
 
        return redirect()->route('shopify.gift-cards.index', $request->query())
            ->with('status', 'Gift card updated.');
    }
 
    public function destroy(Request $request, GiftCard $giftCard, ShopifyService $shopifyService): RedirectResponse
    {
        $shop = $this->resolveShop($request->string('shop')->toString());
 
        if ($shop && $giftCard->shopify_product_id) {
            try {
                $shopifyService->api($shop, 'DELETE', "products/{$giftCard->shopify_product_id}.json");
            } catch (\Throwable $e) {
                // Ignore sync errors
            }
        }
 
        $giftCard->delete();
 
        return redirect()->route('shopify.gift-cards.index', $request->query())
            ->with('status', 'Gift card deleted.');
    }

    private function generateVoucherPool(GiftCard $giftCard): void
    {
        $prefix = strtoupper(trim($giftCard->code_prefix ?: 'GC'));
        $existing = GiftCardVoucher::pluck('code')->toArray();
        $vouchers = [];

        for ($i = 0; $i < 25; $i++) {
            do {
                $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
            } while (in_array($code, $existing));

            $existing[] = $code;

            $vouchers[] = [
                'gift_card_id' => $giftCard->id,
                'code' => $code,
                'original_amount' => $giftCard->amount,
                'remaining_balance' => $giftCard->amount,
                'currency' => 'USD',
                'sender_name' => '',
                'recipient_name' => '',
                'recipient_email' => '',
                'personal_message' => '',
                'scheduled_send_date' => now()->format('Y-m-d'),
                'expires_at' => now()->addDays($giftCard->validity_days ?: 365)->format('Y-m-d'),
                'status' => 'pending_issuance',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        GiftCardVoucher::insert($vouchers);
    }

    private function viewData(Request $request, GiftCard $giftCard): array
    {
        $shopDomain = $request->string('shop')->toString();
        $shop = $this->resolveShop($shopDomain);

        $templates = Schema::hasTable('gift_card_templates')
            ? GiftCardTemplate::query()
                ->when($shop?->id, fn ($query, $shopId) => $query->where('shop_id', $shopId))
                ->latest()
                ->get()
            : collect();

        return [
            'shop' => $shop,
            'shopDomain' => $shopDomain,
            'giftCard' => $giftCard,
            'templates' => $templates,
        ];
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'code_prefix' => ['nullable', 'string', 'max:20'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
            'template_id' => ['nullable', 'integer', 'exists:gift_card_templates,id'],
            'image_upload' => ['nullable', 'file', 'image', 'max:5120'],
        ]);
    }

    private function resolveShop(string $shopDomain): ?Shop
    {
        if ($shopDomain === '' || ! Schema::hasTable('shops')) {
            return null;
        }

        return Shop::query()->where('shopify_domain', $shopDomain)->first();
    }
}
