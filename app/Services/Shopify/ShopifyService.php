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

            if (!$pageExists) {
                $pagePayload = [
                    'page' => [
                        'title' => 'Gift Card',
                        'handle' => 'gift-card',
                        'body_html' => '<div id="gift-card-app-container">
                                <div style="text-align: center; padding: 40px;">
                                    <p>Loading your gift cards...</p>
                                </div>
                            </div>

                            <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                fetch(\'/apps/gift-cards/storefront/gift-cards\')
                                    .then(response => {
                                        if (response.status === 401) {
                                            document.getElementById(\'gift-card-app-container\').innerHTML = 
                                                \'<div style="padding: 20px; text-align: center;"><h3>Please log in to view your gift cards.</h3></div>\';
                                            return null;
                                        }
                                        return response.text();
                                    })
                                    .then(html => {
                                        if (html) {
                                            document.getElementById(\'gift-card-app-container\').innerHTML = html;
                                        }
                                    })
                                    .catch(error => {
                                        console.error(\'Error fetching gift cards:\', error);
                                        document.getElementById(\'gift-card-app-container\').innerHTML = 
                                            \'<div style="padding: 20px; text-align: center; color: red;"><h3>Error loading gift cards. Please try again later.</h3></div>\';
                                    });
                            });
                            </script>'
                    ]
                ];
                $pageCreateResponse = $this->api(
            $shop,
            'POST',
            'pages.json',
            $pagePayload
        );

        Log::info('Gift Card Page Create', [
            'status' => $pageCreateResponse->status(),
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