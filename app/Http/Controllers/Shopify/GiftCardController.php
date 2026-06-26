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
use Illuminate\Support\Facades\Log;
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
                ->with('template')
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

        try {
            $this->syncShopifyProduct($shopifyService, $shop, $giftCard, 'POST');
        } catch (\Throwable $e) {
            Log::error('Gift card product sync failed (POST): ' . $e->getMessage(), ['exception' => $e]);
        }

        // Generate voucher pool
        $this->generateVoucherPool($giftCard);

        try {
            $shopifyService->createStorefrontResources($shop);
        } catch (\Throwable $e) {
            Log::error('Failed to sync storefront resources after gift card creation: ' . $e->getMessage());
        }
 
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
            try {
                $this->syncShopifyProduct($shopifyService, $shop, $giftCard, 'PUT');
            } catch (\Throwable $e) {
                Log::error('Gift card product sync failed (PUT): ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        if ($shop) {
            try {
                $shopifyService->createStorefrontResources($shop);
            } catch (\Throwable $e) {
                Log::error('Failed to sync storefront resources after gift card update: ' . $e->getMessage());
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

        if ($shop) {
            try {
                $shopifyService->createStorefrontResources($shop);
            } catch (\Throwable $e) {
                Log::error('Failed to sync storefront resources after gift card delete: ' . $e->getMessage());
            }
        }
 
        return redirect()->route('shopify.gift-cards.index', $request->query())
            ->with('status', 'Gift card deleted.');
    }

    private function generateVoucherPool(GiftCard $giftCard): void
    {
        $prefix = strtoupper(trim($giftCard->code_prefix ?: ''));
        $existing = GiftCardVoucher::pluck('code')->toArray();
        $vouchers = [];

        for ($i = 0; $i < 25; $i++) {
            do {
                $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $randomPart = '';
                for ($j = 0; $j < 12; $j++) {
                    $randomPart .= $characters[random_int(0, 35)];
                }
                $code = $prefix !== '' ? ($prefix . '-' . $randomPart) : $randomPart;
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
                'expires_at' => now()->addDays((int) ($giftCard->validity_days ?: 365))->format('Y-m-d'),
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
            'template_id' => ['required', 'integer', 'exists:gift_card_templates,id'],
            'image_upload' => ['nullable', 'file', 'image', 'max:5120'],
        ]);
    }

    private function syncShopifyProduct(ShopifyService $shopifyService, Shop $shop, GiftCard $giftCard, string $method): void
    {
        $template = $giftCard->template;
        
        $localPath = null;
        if ($giftCard->image_url) {
            $localPath = storage_path('app/public/' . $giftCard->image_url);
        } elseif ($template && $template->media_url) {
            $localPath = storage_path('app/public/' . $template->media_url);
        } else {
            $localPath = public_path('images/default-gift-card.png');
        }

        $attachment = null;
        if ($localPath && file_exists($localPath)) {
            $attachment = base64_encode(file_get_contents($localPath));
        }

        $images = [];
        if ($attachment) {
            $images[] = [
                'attachment' => $attachment,
                'filename' => basename($localPath) ?: 'image.png',
            ];
        }

        $payload = [
            'product' => [
                'title' => $giftCard->name,
                'body_html' => $this->buildProductBodyHtml($giftCard, $template),
                'vendor' => $template?->name ?: 'ICTECHGiftCard',
                'product_type' => 'Gift Card',
                'status' => $giftCard->active ? 'active' : 'draft',
                'tags' => collect(array_filter([
                    'gift-card',
                    $giftCard->code_prefix ? 'prefix:' . $giftCard->code_prefix : null,
                    $template?->tag ? 'template:' . $template->tag : null,
                ]))->implode(', '),
                'variants' => [
                    [
                        'price' => number_format((float) $giftCard->amount, 2, '.', ''),
                        'requires_shipping' => false,
                        'taxable' => false,
                    ],
                ],
                'images' => $images,
                'metafields' => [
                    [
                        'namespace' => 'seo',
                        'key' => 'hidden',
                        'value' => 1,
                        'type' => 'integer',
                    ],
                ],
            ],
        ];

        if ($method === 'POST') {
            $response = $shopifyService->api($shop, 'POST', 'products.json', $payload);
            if ($response->successful()) {
                $resData = $response->json();
                $giftCard->shopify_product_id = $resData['product']['id'] ?? null;
                $giftCard->shopify_product_variant_id = $resData['product']['variants'][0]['id'] ?? null;
                $giftCard->save();
                return;
            }

            Log::warning('Gift card product create failed', [
                'gift_card_id' => $giftCard->id,
                'response' => $response->body(),
            ]);

            $fallbackCreated = $this->createShopifyProductViaGraphql($shopifyService, $shop, $giftCard, $template, $attachment, $localPath);
            if ($fallbackCreated) {
                return;
            }

            return;
        }

        if (! $giftCard->shopify_product_id) {
            return;
        }

        $payload['product']['id'] = $giftCard->shopify_product_id;
        $response = $shopifyService->api($shop, 'PUT', "products/{$giftCard->shopify_product_id}.json", $payload);
        if (! $response->successful()) {
            Log::warning('Gift card product update failed', [
                'gift_card_id' => $giftCard->id,
                'product_id' => $giftCard->shopify_product_id,
                'response' => $response->body(),
            ]);
        }

        if ($giftCard->shopify_product_variant_id) {
            $varPayload = [
                'variant' => [
                    'id' => $giftCard->shopify_product_variant_id,
                    'price' => number_format((float) $giftCard->amount, 2, '.', ''),
                ],
            ];
            $variantResponse = $shopifyService->api($shop, 'PUT', "variants/{$giftCard->shopify_product_variant_id}.json", $varPayload);
            if (! $variantResponse->successful()) {
                Log::warning('Gift card product variant update failed', [
                    'gift_card_id' => $giftCard->id,
                    'product_variant_id' => $giftCard->shopify_product_variant_id,
                    'response' => $variantResponse->body(),
                ]);
            }
        }
    }

    private function buildProductBodyHtml(GiftCard $giftCard, ?GiftCardTemplate $template): string
    {
        $parts = [
            '<p><strong>Gift Card</strong></p>',
            '<p>Amount: ' . number_format((float) $giftCard->amount, 2) . '</p>',
            '<p>Code Prefix: ' . e($giftCard->code_prefix ?: 'GC') . '</p>',
            '<p>Validity: ' . (int) ($giftCard->validity_days ?: 365) . ' days</p>',
        ];

        if ($template) {
            $parts[] = '<p>Template: ' . e($template->name) . '</p>';
            if ($template->tag) {
                $parts[] = '<p>Template Tag: ' . e($template->tag) . '</p>';
            }
        }

        return implode('', $parts);
    }

    private function createShopifyProductViaGraphql(ShopifyService $shopifyService, Shop $shop, GiftCard $giftCard, ?GiftCardTemplate $template, ?string $attachment, ?string $localPath): bool
    {
        $query = <<<'GQL'
mutation productCreate($input: ProductInput!) {
  productCreate(input: $input) {
    product {
      id
      variants(first: 1) {
        nodes {
          id
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}
GQL;

        $variables = [
            'input' => [
                'title' => $giftCard->name,
                'descriptionHtml' => $this->buildProductBodyHtml($giftCard, $template),
                'productType' => 'Gift Card',
                'vendor' => $template?->name ?: 'ICTECHGiftCard',
                'status' => $giftCard->active ? 'ACTIVE' : 'DRAFT',
                'tags' => array_values(array_filter([
                    'gift-card',
                    $giftCard->code_prefix ? 'prefix:' . $giftCard->code_prefix : null,
                    $template?->tag ? 'template:' . $template->tag : null,
                ])),
                'variants' => [
                    [
                        'price' => number_format((float) $giftCard->amount, 2, '.', ''),
                    ],
                ],
                'metafields' => [
                    [
                        'namespace' => 'seo',
                        'key' => 'hidden',
                        'value' => '1',
                        'type' => 'integer',
                    ],
                ],
            ],
        ];

        $response = $shopifyService->api($shop, 'POST', 'graphql.json', [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (! $response->successful()) {
            Log::warning('Gift card product GraphQL create failed', [
                'gift_card_id' => $giftCard->id,
                'response' => $response->body(),
            ]);
            return false;
        }

        $data = $response->json('data.productCreate');
        $errors = $data['userErrors'] ?? [];
        if (! empty($errors)) {
            Log::warning('Gift card product GraphQL create user errors', [
                'gift_card_id' => $giftCard->id,
                'errors' => $errors,
            ]);
            return false;
        }

        $productGid = $data['product']['id'] ?? null;
        $variantGid = $data['product']['variants']['nodes'][0]['id'] ?? null;

        $giftCard->shopify_product_id = $this->extractShopifyNumericId($productGid);
        $giftCard->shopify_product_variant_id = $this->extractShopifyNumericId($variantGid);
        $giftCard->save();

        if ($attachment && $giftCard->shopify_product_id) {
            try {
                $shopifyService->api($shop, 'POST', "products/{$giftCard->shopify_product_id}/images.json", [
                    'image' => [
                        'attachment' => $attachment,
                        'filename' => basename($localPath) ?: 'image.png',
                    ],
                ]);
            } catch (\Throwable $imageError) {
                Log::warning('Gift card product image sync failed after GraphQL create', [
                    'gift_card_id' => $giftCard->id,
                    'product_id' => $giftCard->shopify_product_id,
                    'error' => $imageError->getMessage(),
                ]);
            }
        }

        return true;
    }

    private function extractShopifyNumericId(?string $gid): ?string
    {
        if (! is_string($gid) || $gid === '') {
            return null;
        }

        if (preg_match('/(\d+)$/', $gid, $matches)) {
            return $matches[1];
        }

        return $gid;
    }

    private function resolveShop(string $shopDomain): ?Shop
    {
        if ($shopDomain === '' || ! Schema::hasTable('shops')) {
            return null;
        }

        return Shop::query()->where('shopify_domain', $shopDomain)->first();
    }
}
