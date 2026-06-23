<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    public function sanitizeShopDomain(string $shopDomain): string
    {
        $shopDomain = preg_replace('/^https?:\/\//i', '', $shopDomain);
        $shopDomain = trim($shopDomain);

        if (preg_match('/admin\.shopify\.com\/store\/([a-zA-Z0-9\-]+)/i', $shopDomain, $matches)) {
            return $matches[1] . '.myshopify.com';
        }

        $parts = explode('/', $shopDomain);
        $host = strtolower($parts[0]);

        if (! str_ends_with($host, '.myshopify.com')) {
            if (preg_match('/^[a-zA-Z0-9\-]+$/', $host)) {
                return $host . '.myshopify.com';
            }
        }

        return $host;
    }

    public function installUrl(string $shopDomain): string
    {
        $shopDomain = $this->sanitizeShopDomain($shopDomain);
        $scopes = implode(',', config('shopify.scopes'));
        $state = bin2hex(random_bytes(16));

        session(['shopify_oauth_state' => $state]);

        return sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shopDomain,
            config('shopify.api_key'),
            urlencode($scopes),
            urlencode(route('shopify.callback')),
            $state
        );
    }

    public function findShop(string $shopDomain): ?Shop
    {
        return Shop::query()
            ->where('shopify_domain', $this->sanitizeShopDomain($shopDomain))
            ->first();
    }

    public function verifyCallback(array $payload): bool
    {
        $hmac = Arr::pull($payload, 'hmac');

        Arr::pull($payload, 'signature');

        if (! is_string($hmac)) {
            return false;
        }

        ksort($payload);

        $message = collect($payload)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&');

        $calculated = hash_hmac(
            'sha256',
            $message,
            (string) config('shopify.api_secret')
        );

        return hash_equals($calculated, $hmac);
    }

    public function exchangeToken(string $shopDomain, string $code): ?string
    {
        $shopDomain = $this->sanitizeShopDomain($shopDomain);

        $response = Http::asJson()->post(
            sprintf('https://%s/admin/oauth/access_token', $shopDomain),
            [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'code' => $code,
            ]
        );

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? ($data['access_token'] ?? null) : null;
    }

    public function embeddedAppUrl(string $shopDomain): string
    {
        $shopDomain = $this->sanitizeShopDomain($shopDomain);
        $shopHandle = str_replace('.myshopify.com', '', $shopDomain);

        return sprintf(
            'https://admin.shopify.com/store/%s/apps/%s',
            $shopHandle,
            config('shopify.app_handle')
        );
    }

    public function api(Shop $shop, string $method, string $path, array $data = [], int $retries = 3): Response
    {
        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'admin/oauth/') || str_starts_with($cleanPath, 'oauth/')) {
            $url = sprintf(
                'https://%s/%s',
                $shop->shopify_domain,
                str_starts_with($cleanPath, 'admin/') ? $cleanPath : 'admin/' . $cleanPath
            );
        } else {
            $url = sprintf(
                'https://%s/admin/api/%s/%s',
                $shop->shopify_domain,
                config('shopify.api_version'),
                $cleanPath
            );
        }

        for ($i = 0; $i < $retries; $i++) {
            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type' => 'application/json',
            ]);

            $methodName = strtolower($method);

            $response = match ($methodName) {
                'get' => $request->get($url, $data),
                'post' => $request->post($url, $data),
                'put' => $request->put($url, $data),
                'delete' => $request->delete($url, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: $method"),
            };

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After', 2);
                sleep($retryAfter);
                continue;
            }

            if ($response->status() === 401) {
                throw new \Exception("Unauthorized");
            }

            return $response;
        }

        throw new \Exception("Shopify API rate limit exceeded after {$retries} retries.");
    }

    public function createStorefrontResources(Shop $shop): void
    {
        try {
            // 1. Check if the Gift Card page exists
            $pagesResponse = $this->api($shop, 'GET', 'pages.json', ['handle' => 'gift-card']);
            $pageExists = false;
            $giftCardPageId = null;
            
            if ($pagesResponse->successful()) {
                $pages = $pagesResponse->json()['pages'] ?? [];
                foreach ($pages as $page) {
                    if (($page['handle'] ?? '') === 'gift-card') {
                        $pageExists = true;
                        $giftCardPageId = $page['id'] ?? null;
                        break;
                    }
                }
            }

            // Build the page body using the Dawn theme's exact CSS classes so the
            // cards are pixel-identical to /collections/all — hover effects, badge,
            // price, and product link all work out of the box because the theme
            // CSS is already loaded on every storefront page.
            $bodyHtml = <<<'HTML'
<style>
/* ── Grid ── */
#gc-sort-bar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;border-bottom:1px solid #e5e7eb;padding-bottom:1rem}
#gc-sort-bar .gc-count{font-size:.875rem;color:#6b7280}
#gc-sort-bar .gc-sort-wrap{display:flex;align-items:center;gap:.5rem}
#gc-sort-bar label{font-size:.875rem;color:#121212;white-space:nowrap}
#gc-sort-bar select{border:1px solid rgba(18,18,18,.3);padding:.35rem 2rem .35rem .75rem;font-size:.875rem;background:transparent;cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='7'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23121212' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .6rem center;min-width:160px}
#gc-product-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:3rem 2rem;list-style:none;padding:0;margin:0}
@media(max-width:749px){#gc-product-grid{grid-template-columns:repeat(2,1fr);gap:2rem 1rem}}
@media(max-width:479px){#gc-product-grid{grid-template-columns:1fr}}
/* ── Card ── */
.gc-card-link{display:block;text-decoration:none;color:inherit}
.gc-card{position:relative;border-radius:2px}
/* Image container — uses padding-bottom trick for reliable square ratio */
.gc-media-wrap{position:relative;width:100%;padding-bottom:100%;overflow:hidden;background:#f3f4f6}
.gc-media-wrap img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
.gc-media-wrap .gc-ph{position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#9ca3af}
/* Hover: scale image */
.gc-card-link:hover .gc-media-wrap img{transform:scale(1.05)}
/* Badge */
.gc-badge-wrap{position:absolute;bottom:.75rem;left:.75rem;z-index:2}
.gc-badge{display:inline-block;padding:.3rem .7rem;font-size:.6875rem;letter-spacing:.07em;font-weight:500;text-transform:uppercase}
.gc-badge--sold{background:#1c1c1c;color:#fff}
.gc-badge--sale{background:#b91c1c;color:#fff}
/* Info */
.gc-info{padding:.75rem 0 0}
.gc-title{font-size:.9375rem;font-weight:400;color:#121212;margin:0 0 .35rem;line-height:1.4}
.gc-card-link:hover .gc-title{text-decoration:underline;text-underline-offset:3px}
.gc-price{font-size:.9375rem;color:#121212;margin:0}
.gc-price--sale{color:#b91c1c}
.gc-price-was{color:#6b7280;text-decoration:line-through;margin-left:.35rem;font-size:.875rem}
</style>

<div id="gc-listing">
  <p style="text-align:center;padding:40px;color:#6b7280">Loading gift cards&hellip;</p>
</div>

<script>
(function(){
  var state={products:[]};

  function esc(s){
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function fmt(price){
    return '$'+parseFloat(price).toFixed(2);
  }

  function buildCard(p){
    var v=p.variants&&p.variants[0];
    var img=p.images&&p.images.length?p.images[0].src:'';
    var img2=p.images&&p.images.length>1?p.images[1].src:'';
    var price=v?fmt(v.price):'';
    var comparePrice=v&&v.compare_at_price&&parseFloat(v.compare_at_price)>parseFloat(v.price)?fmt(v.compare_at_price):'';
    var sold=v?!v.available:true;
    var onSale=!!comparePrice;
    var url='/products/'+p.handle;

    /* Image / placeholder */
    var mediaInner='';
    if(img){
      var img2html=img2?'<img src="'+esc(img2)+'" alt="'+esc(p.title)+'" loading="lazy" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .4s">':'';
      mediaInner='<img src="'+esc(img)+'" alt="'+esc(p.title)+'" loading="lazy">'+img2html;
    } else {
      mediaInner='<div class="gc-ph"><svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></div>';
    }

    /* Badge */
    var badge='';
    if(sold) badge='<span class="gc-badge gc-badge--sold">Sold out</span>';
    else if(onSale) badge='<span class="gc-badge gc-badge--sale">Sale</span>';

    /* Price */
    var priceHtml='';
    if(onSale){
      priceHtml='<span class="gc-price gc-price--sale">'+price+'</span><s class="gc-price-was">'+comparePrice+'</s>';
    } else {
      priceHtml='<span class="gc-price">'+price+'</span>';
    }

    return '<li>'
      +'<a href="'+url+'" class="gc-card-link">'
        +'<div class="gc-card">'
          +'<div class="gc-media-wrap">'+mediaInner+'<div class="gc-badge-wrap">'+badge+'</div></div>'
          +'<div class="gc-info">'
            +'<p class="gc-title">'+esc(p.title)+'</p>'
            +'<div>'+priceHtml+'</div>'
          +'</div>'
        +'</div>'
      +'</a>'
    +'</li>';
  }

  function renderSort(val){
    var ps=state.products.slice();
    if(val==='price-asc') ps.sort(function(a,b){return parseFloat((a.variants[0]||{}).price||0)-parseFloat((b.variants[0]||{}).price||0);});
    else if(val==='price-desc') ps.sort(function(a,b){return parseFloat((b.variants[0]||{}).price||0)-parseFloat((a.variants[0]||{}).price||0);});
    else if(val==='title-asc') ps.sort(function(a,b){return a.title.localeCompare(b.title);});
    else if(val==='title-desc') ps.sort(function(a,b){return b.title.localeCompare(a.title);});
    document.getElementById('gc-product-grid').innerHTML=ps.map(buildCard).join('');
    var n=ps.length;
    document.querySelector('.gc-count').textContent=n+(n===1?' Gift Card':' Gift Cards');
  }

  document.addEventListener('DOMContentLoaded',function(){
    var el=document.getElementById('gc-listing');
    fetch('/products.json?limit=250')
      .then(function(r){return r.json();})
      .then(function(d){
        var all=d.products||[];
        var ps=all.filter(function(p){
          var t=(p.product_type||'').toLowerCase().replace(/\s+/g,'');
          return t==='giftcard'||p.vendor==='Gift Card App';
        });
        if(!ps.length){
          el.innerHTML='<p style="text-align:center;padding:40px;color:#6b7280">No gift cards are currently available.</p>';
          return;
        }
        state.products=ps;
        el.innerHTML=''
          +'<div id="gc-sort-bar">'
            +'<span class="gc-count"></span>'
            +'<div class="gc-sort-wrap">'
              +'<label for="gc-sort">Sort by:</label>'
              +'<select id="gc-sort">'
                +'<option value="featured">Featured</option>'
                +'<option value="price-asc">Price: Low to High</option>'
                +'<option value="price-desc">Price: High to Low</option>'
                +'<option value="title-asc">Alphabetically, A-Z</option>'
                +'<option value="title-desc">Alphabetically, Z-A</option>'
              +'</select>'
            +'</div>'
          +'</div>'
          +'<ul id="gc-product-grid"></ul>';
        document.getElementById('gc-sort').addEventListener('change',function(){renderSort(this.value);});
        renderSort('featured');
      })
      .catch(function(){
        el.innerHTML='<p style="text-align:center;padding:40px;color:#dc2626">Unable to load gift cards. Please try again later.</p>';
      });
  });
})();
</script>
HTML;

            if ($pageExists && $giftCardPageId) {
                // Update the existing page body so it uses the current template.
                $this->api($shop, 'PUT', "pages/{$giftCardPageId}.json", [
                    'page' => ['id' => $giftCardPageId, 'body_html' => $bodyHtml],
                ]);
            }

            if (!$pageExists) {
                $pagePayload = [
                    'page' => [
                        'title'     => 'Gift Card',
                        'handle'    => 'gift-card',
                        'body_html' => $bodyHtml,
                    ],
                ];
                $pageCreateResponse = $this->api($shop, 'POST', 'pages.json', $pagePayload);

                Log::info('Gift Card Page Create', [
                    'status'   => $pageCreateResponse->status(),
                    'response' => $pageCreateResponse->json(),
                ]);

                if (!$pageCreateResponse->successful()) {
                    Log::error('Gift Card Page Creation Failed', [
                        'response' => $pageCreateResponse->body(),
                    ]);
                    return;
                }
                $giftCardPageId = $pageCreateResponse->json()['page']['id'] ?? null;
            }


            // 2. Fetch menus via GraphQL
            $graphqlQuery = [
                'query' => 'query {
                    menus(first: 20) {
                        edges {
                            node {
                                id
                                title
                                handle
                                items {
                                    title
                                    url
                                    type
                                    resourceId
                                    items {
                                        title
                                        url
                                        type
                                        resourceId
                                    }
                                }
                            }
                        }
                    }
                }'
            ];
            
            $graphqlResponse = $this->api($shop, 'POST', 'graphql.json', $graphqlQuery);
            if ($graphqlResponse->successful()) {
                $resData = $graphqlResponse->json();
                if (isset($resData['errors'])) {
                    \Illuminate\Support\Facades\Log::error('GraphQL menus query error: ' . json_encode($resData['errors']));
                }
                $menus = $resData['data']['menus']['edges'] ?? [];
                $mainMenu = null;
                foreach ($menus as $mEdge) {

                    $mNode = $mEdge['node'] ?? null;

                    if (!$mNode) {
                        continue;
                    }

                    $handle = strtolower($mNode['handle'] ?? '');
                    $title = strtolower($mNode['title'] ?? '');

                    if (
                        in_array($handle, [
                            'main-menu',
                            'main-navigation',
                            'primary-menu',
                            'navigation'
                        ])
                        ||
                        str_contains($title, 'main')
                        ||
                        str_contains($title, 'navigation')
                    ) {
                        $mainMenu = $mNode;
                        break;
                    }
                }

                if ($mainMenu) {
                    $existingItems = $mainMenu['items'] ?? [];
                    $hasGiftCardLink = false;
                    foreach ($existingItems as $item) {
                        if (($item['url'] ?? '') === '/pages/gift-card' || strtolower($item['title'] ?? '') === 'gift card') {
                            $hasGiftCardLink = true;
                            break;
                        }
                    }

                    if (!$hasGiftCardLink) {
                        $formattedItems = $this->formatMenuItemsForUpdate($existingItems);
                        if ($giftCardPageId) {
                            $formattedItems[] = [
                                'title' => 'Gift Card',
                                'url' => '/pages/gift-card',
                                'type' => 'PAGE',
                                'resourceId' => 'gid://shopify/Page/' . $giftCardPageId
                            ];
                        } else {
                            $formattedItems[] = [
                                'title' => 'Gift Card',
                                'url' => '/pages/gift-card',
                                'type' => 'HTTP'
                            ];
                        }

                        $updateMutation = [
                            'query' => 'mutation menuUpdate($id: ID!, $title: String!, $items: [MenuItemUpdateInput!]!) {
                                menuUpdate(id: $id, title: $title, items: $items) {
                                    menu {
                                        id
                                        title
                                    }
                                    userErrors {
                                        field
                                        message
                                    }
                                }
                            }',
                            'variables' => [
                                'id' => $mainMenu['id'],
                                'title' => $mainMenu['title'],
                                'items' => $formattedItems
                            ]
                        ];
                        $updateRes = $this->api($shop, 'POST', 'graphql.json', $updateMutation);
                        if ($updateRes->successful()) {
                            $updateData = $updateRes->json();
                            if (isset($updateData['errors'])) {
                                \Illuminate\Support\Facades\Log::error('GraphQL menuUpdate query error: ' . json_encode($updateData['errors']));
                            }
                            $userErrors = $updateData['data']['menuUpdate']['userErrors'] ?? [];
                            if (!empty($userErrors)) {
                                \Illuminate\Support\Facades\Log::error('GraphQL menuUpdate user errors: ' . json_encode($userErrors));
                            }
                        } else {
                            \Illuminate\Support\Facades\Log::error('GraphQL menuUpdate request failed: ' . $updateRes->status());
                        }
                    }
                }
            } else {
                \Illuminate\Support\Facades\Log::error('GraphQL menus request failed: ' . $graphqlResponse->status());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error creating storefront resources: ' . $e->getMessage());
        }
    }

    private function formatMenuItemsForUpdate(array $items): array
    {
        $formatted = [];
        foreach ($items as $item) {
            $formattedItem = [
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'type' => $item['type'] ?? 'HTTP',
            ];
            if (!empty($item['resourceId'])) {
                $formattedItem['resourceId'] = $item['resourceId'];
            }
            if (!empty($item['items'])) {
                $formattedItem['items'] = $this->formatMenuItemsForUpdate($item['items']);
            }
            $formatted[] = $formattedItem;
        }
        return $formatted;
    }
}