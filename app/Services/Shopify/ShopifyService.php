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
            // Inject script to customize the Cart page images via active theme asset
            try {
                $scriptUrl = url('/js/cart-customizer.js');
                if (str_starts_with($scriptUrl, 'http://')) {
                    $scriptUrl = str_replace('http://', 'https://', $scriptUrl);
                }

                $themesResponse = $this->api($shop, 'GET', 'themes.json');
                if ($themesResponse->successful()) {
                    $themes = $themesResponse->json()['themes'] ?? [];
                    $activeThemeId = null;
                    foreach ($themes as $theme) {
                        if (($theme['role'] ?? '') === 'main') {
                            $activeThemeId = $theme['id'];
                            break;
                        }
                    }

                    if ($activeThemeId) {
                        $assetResponse = $this->api($shop, 'GET', "themes/{$activeThemeId}/assets.json", [
                            'asset[key]' => 'layout/theme.liquid'
                        ]);

                        if ($assetResponse->successful()) {
                            $asset = $assetResponse->json()['asset'] ?? [];
                            $value = $asset['value'] ?? '';

                            if (!empty($value)) {
                                // Clean up any old script injections for cart-customizer.js
                                $pattern = '/<script[^>]*src="[^"]*\/cart-customizer\.js"[^>]*><\/script>\s*/i';
                                $value = preg_replace($pattern, '', $value);

                                // Inject the new one right before </body>
                                $newScriptTag = '<script src="' . htmlspecialchars($scriptUrl) . '" defer="defer"></script>';
                                if (strpos($value, '</body>') !== false) {
                                    $value = str_replace('</body>', $newScriptTag . "\n  </body>", $value);
                                } else {
                                    $value .= "\n" . $newScriptTag;
                                }

                                $this->api($shop, 'PUT', "themes/{$activeThemeId}/assets.json", [
                                    'asset' => [
                                        'key' => 'layout/theme.liquid',
                                        'value' => $value
                                    ]
                                ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to inject theme script: ' . $e->getMessage());
            }

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

            // Fetch active templates
            $templates = \App\Models\GiftCardTemplate::where('shop_id', $shop->id)
                ->where('active', true)
                ->get()
                ->map(function ($t) {
                    $mediaUrl = $t->media_url;
                    $dataUri  = null;

                    // Try to read from local disk and convert to base64 data URI
                    // This eliminates ALL cross-origin/CORS/ngrok issues on the storefront
                    if ($mediaUrl && !str_starts_with($mediaUrl, 'http')) {
                        $localPath = storage_path('app/public/' . $mediaUrl);
                        if (file_exists($localPath)) {
                            $mime    = mime_content_type($localPath) ?: 'image/png';
                            $b64     = base64_encode(file_get_contents($localPath));
                            $dataUri = 'data:' . $mime . ';base64,' . $b64;
                        }
                    }

                    return [
                        'id'             => $t->id,
                        'name'           => $t->name,
                        'tag'            => $t->tag ?: 'Various',
                        'media_url'      => $dataUri ?: ($mediaUrl ? url('/storage/' . $mediaUrl) : null),
                        'real_media_url' => $mediaUrl ? url('/storage/' . $mediaUrl) : null,
                    ];
                });

            // Fetch active gift cards
            $giftCards = \App\Models\GiftCard::where('shop_id', $shop->id)
                ->where('active', true)
                ->whereNotNull('shopify_product_variant_id')
                ->where('shopify_product_variant_id', '!=', '')
                ->get()
                ->map(function ($g) {
                    return [
                        'id' => $g->id,
                        'variant_id' => $g->shopify_product_variant_id,
                        'amount' => (float) $g->amount,
                        'name' => $g->name,
                    ];
                })
                ->sortBy('amount')
                ->values();

            $storefrontText = $shop->getSetting('storefrontText') ?: '<p>Delight your loved ones in just a few clicks! Birthday, Valentine\'s Day, weddings, Christmas... Send a personalized gift card by email to the address of your choice. The amount will then be available as a voucher valid across our entire site.</p>';

            $appUrl = url('/');
            $templatesJson = json_encode($templates);
            $giftCardsJson = json_encode($giftCards);

            $bodyHtml = <<<HTML
<style>
/* ── Main Layout ── */
.gc-page-container {
  max-width: 800px;
  margin: 0 auto;
  font-family: inherit;
  color: #121212;
  padding: 20px 0;
}
.gc-description {
  font-size: 1rem;
  line-height: 1.6;
  color: #4b5563;
  margin-bottom: 2rem;
}
.gc-step-title {
  font-size: 1.05rem !important;
  font-weight: 600;
  margin: 1.5rem 0 1rem 0 !important;
  border-bottom: 2px solid #e5e7eb;
  padding-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #111827;
}
.gc-step-num {
  background: #111827;
  color: #ffffff;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1;
}

/* ── Step 1: Delivery Method ── */
.gc-delivery-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.gc-delivery-btn {
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 1.25rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  user-select: none;
}
.gc-delivery-btn:hover {
  border-color: #9ca3af;
  background: #f9fafb;
}
.gc-delivery-btn.active {
  border-color: #121212;
  background: #f3f4f6;
  box-shadow: 0 0 0 1px #121212;
}
.gc-delivery-btn svg {
  width: 28px;
  height: 28px;
  color: #4b5563;
  transition: color 0.2s ease;
}
.gc-delivery-btn.active svg {
  color: #121212;
}
.gc-delivery-label {
  font-size: 0.875rem;
  font-weight: 600;
}

/* ── Step 2: Templates ── */
.gc-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.gc-tab-btn {
  background: #f3f4f6;
  border: 1px solid transparent;
  border-radius: 20px;
  padding: 0.5rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  color: #4b5563;
}
.gc-tab-btn:hover {
  background: #e5e7eb;
}
.gc-tab-btn.active {
  background: #121212;
  color: #ffffff;
}
.gc-template-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}
@media(max-width: 768px) {
  .gc-template-grid { grid-template-columns: repeat(3, 1fr); }
}
@media(max-width: 480px) {
  .gc-template-grid { grid-template-columns: repeat(2, 1fr); }
}
.gc-template-item {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  aspect-ratio: 1.586;
  cursor: pointer;
  border: 2px solid transparent;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  transition: all 0.25s ease;
  background: #f3f4f6;
}
.gc-template-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.gc-template-item.active {
  border-color: #10b981;
}
.gc-template-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  pointer-events: none;
}
.gc-checkmark-overlay {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(16, 185, 129, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
  pointer-events: none;
}
.gc-template-item.active .gc-checkmark-overlay {
  opacity: 1;
}
.gc-checkmark-circle {
  background: #ffffff;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  color: #10b981;
}

/* ── Step 3: Information ── */
.gc-form-layout {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 2.5rem;
}
@media(max-width: 768px) {
  .gc-form-layout { grid-template-columns: 1fr; gap: 2rem; }
}
.gc-form-fields {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.gc-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.gc-form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}
.gc-input, .gc-select {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.75rem;
  font-size: 0.9375rem;
  outline: none;
  transition: border-color 0.2s ease;
  background: #ffffff;
  width: 100%;
  box-sizing: border-box;
}
.gc-input:focus, .gc-select:focus {
  border-color: #121212;
  box-shadow: 0 0 0 1px #121212;
}
.gc-textarea {
  min-height: 100px;
  resize: vertical;
  font-family: inherit;
}
.gc-char-counter {
  font-size: 0.75rem;
  color: #6b7280;
  text-align: right;
  margin-top: 0.2rem;
}

/* ── Live Preview Card ── */
.gc-preview-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
.gc-preview-title {
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: 0.05em;
  margin: 0;
  align-self: flex-start;
}
.gc-card-preview {
  width: 100%;
  max-width: 380px;
  aspect-ratio: 1.586;
  border-radius: 12px;
  background: linear-gradient(135deg, #1e3a8a 0%, #0d9488 100%);
  position: relative;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  color: #ffffff;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 1.5rem;
  background-size: cover;
  background-position: center;
  transition: background-image 0.3s ease;
  box-sizing: border-box;
}
.gc-preview-logo {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  opacity: 0.9;
}
.gc-preview-amount {
  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 1.125rem;
  font-weight: 600;
  border: 1px solid rgba(255, 255, 255, 0.3);
}
.gc-preview-msg {
  font-size: 0.875rem;
  line-height: 1.4;
  margin-top: 1.5rem;
  margin-bottom: auto;
  opacity: 0.9;
  font-style: italic;
  max-height: 60px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
}
.gc-preview-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  font-size: 0.75rem;
  opacity: 0.85;
}
.gc-preview-names {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

/* ── Actions ── */
.gc-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2.5rem;
  justify-content: flex-end;
}
.gc-btn {
  border-radius: 6px;
  padding: 0.95rem 2.25rem;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  outline: none;
  border: none;
}
.gc-btn-secondary {
  background: #ffffff;
  border: 1px solid #d1d5db;
  color: #4b5563;
}
.gc-btn-secondary:hover {
  background: #f9fafb;
  border-color: #9ca3af;
}
.gc-btn-primary {
  background: #121212;
  border: 1px solid #121212;
  color: #ffffff;
}
.gc-btn-primary:hover {
  background: #2a2a2a;
}
.gc-btn-primary:disabled {
  background: #9ca3af;
  border-color: #9ca3af;
  cursor: not-allowed;
}

/* ── Validation Error ── */
.gc-error-text {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.2rem;
  display: none;
}
.gc-input.invalid, .gc-select.invalid {
  border-color: #ef4444;
}
</style>

<div class="gc-page-container">
  <div class="gc-description">
    {$storefrontText}
  </div>

  <!-- Step 1: Delivery Method -->
  <h4 class="gc-step-title">
    <span class="gc-step-num">1</span> Select delivery method
  </h4>
  <div class="gc-delivery-grid">
    <div class="gc-delivery-btn active" data-method="print">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
      </svg>
      <span class="gc-delivery-label">Print at home</span>
    </div>
    <div class="gc-delivery-btn" data-method="email">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
      </svg>
      <span class="gc-delivery-label">Send by email</span>
    </div>
    <div class="gc-delivery-btn" data-method="post">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-5 0h5"></path>
      </svg>
      <span class="gc-delivery-label">Send by post</span>
    </div>
  </div>

  <!-- Step 2: Select Template -->
  <h4 class="gc-step-title">
    <span class="gc-step-num">2</span> Select a template
  </h4>
  <div class="gc-tabs" id="gc-tag-tabs">
    <!-- Tabs will be rendered here dynamically -->
  </div>
  <div class="gc-template-grid" id="gc-templates-container">
    <!-- Templates will be rendered here dynamically -->
  </div>

  <!-- Step 3: Gift Card Information -->
  <h4 class="gc-step-title">
    <span class="gc-step-num">3</span> Gift Card Information
  </h4>
  
  <div class="gc-form-layout">
    <div class="gc-form-fields">
      
      <!-- Amount Selection -->
      <div class="gc-form-group">
        <label for="gc-field-amount">Amount</label>
        <select id="gc-field-amount" class="gc-select">
          <!-- Amounts will be loaded dynamically -->
        </select>
        <span class="gc-error-text" id="err-amount">Please select an amount.</span>
      </div>

      <!-- Sender Name -->
      <div class="gc-form-group">
        <label for="gc-field-sender">From. Your name</label>
        <input type="text" id="gc-field-sender" class="gc-input" placeholder="Your name">
        <span class="gc-error-text" id="err-sender">Sender Name is required.</span>
      </div>

      <!-- Recipient Name -->
      <div class="gc-form-group">
        <label for="gc-field-recipient">To. Recipient Name</label>
        <input type="text" id="gc-field-recipient" class="gc-input" placeholder="Recipient's name">
        <span class="gc-error-text" id="err-recipient">Recipient Name is required.</span>
      </div>

      <!-- Recipient Email (only if Send by email is active) -->
      <div class="gc-form-group" id="gc-email-group" style="display: none;">
        <label for="gc-field-email">To. Recipient Email</label>
        <input type="email" id="gc-field-email" class="gc-input" placeholder="recipient@example.com">
        <span class="gc-error-text" id="err-email">Valid Recipient Email is required.</span>
      </div>

      <!-- Personal Message -->
      <div class="gc-form-group">
        <label for="gc-field-message">Enter your message (Optional)</label>
        <textarea id="gc-field-message" class="gc-input gc-textarea" maxlength="500" placeholder="Write a nice message here..."></textarea>
        <div class="gc-char-counter"><span id="gc-char-count">0</span> / 500</div>
        <span class="gc-error-text" id="err-message">Message must not exceed 500 characters.</span>
      </div>

    </div>

    <!-- Live Preview Panel -->
    <div class="gc-preview-panel">
      <h4 class="gc-preview-title">Live Preview</h4>
      <div class="gc-card-preview" id="gc-preview-card">
        <div class="gc-preview-logo">Gift Card</div>
        <div class="gc-preview-amount" id="gc-preview-amount-val">$0.00</div>
        <div class="gc-preview-msg" id="gc-preview-msg-val">Happy gifting!</div>
        <div class="gc-preview-footer">
          <div class="gc-preview-names">
            <span id="gc-preview-to-val">To: Recipient</span>
            <span id="gc-preview-from-val">From: Sender</span>
          </div>
          <div>Gift Card Code: XXXXXXXXXXXX</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="gc-actions">
    <button class="gc-btn gc-btn-secondary" id="gc-preview-btn">Preview PDF</button>
    <button class="gc-btn gc-btn-primary" id="gc-add-to-cart-btn">ADD TO CART</button>
  </div>
</div>

<script>
(function() {
  var state = {
    templates: {$templatesJson},
    giftCards: {$giftCardsJson},
    appUrl: '{$appUrl}',
    selectedDelivery: 'print',
    selectedTemplateId: null,
    selectedTemplateUrl: '',
    selectedTemplateRealUrl: '',
    selectedTemplateName: '',
    selectedAmount: 0,
    selectedVariantId: null
  };

  function init() {
    // 1. Render delivery method triggers
    var deliveryBtns = document.querySelectorAll('.gc-delivery-btn');
    deliveryBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        deliveryBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        var method = this.getAttribute('data-method');
        state.selectedDelivery = method;
        
        var emailGroup = document.getElementById('gc-email-group');
        if (method === 'email') {
          emailGroup.style.display = 'flex';
        } else {
          emailGroup.style.display = 'none';
        }
        updatePreview();
      });
    });

    // 2. Render Tag Tabs
    renderTabs();

    // 3. Render Templates Grid
    renderTemplates();

    // 4. Render Amounts
    renderAmounts();

    // 5. Setup Live Preview Events
    setupLivePreviewEvents();

    // 6. Setup Buttons Actions
    setupActions();

    // Select default template and amount
    if (state.templates.length > 0) {
      selectTemplate(state.templates[0].id);
    }
    if (state.giftCards.length > 0) {
      document.getElementById('gc-field-amount').value = state.giftCards[0].variant_id;
      handleAmountChange(state.giftCards[0].variant_id);
    }
  }

  function renderTabs() {
    var tags = ['All'];
    state.templates.forEach(function(t) {
      if (t.tag && tags.indexOf(t.tag) === -1) {
        tags.push(t.tag);
      }
    });

    var tabsContainer = document.getElementById('gc-tag-tabs');
    tabsContainer.innerHTML = '';
    tags.forEach(function(tag) {
      var btn = document.createElement('button');
      btn.className = 'gc-tab-btn' + (tag === 'All' ? ' active' : '');
      btn.innerText = tag;
      btn.setAttribute('data-tag', tag);
      btn.addEventListener('click', function() {
        document.querySelectorAll('.gc-tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterTemplates(tag);
      });
      tabsContainer.appendChild(btn);
    });
  }

  function filterTemplates(tag) {
    var items = document.querySelectorAll('.gc-template-item');
    items.forEach(function(item) {
      var itemTag = item.getAttribute('data-tag');
      if (tag === 'All' || itemTag === tag) {
        item.style.display = 'block';
      } else {
        item.style.display = 'none';
      }
    });
  }

  function renderTemplates() {
    var container = document.getElementById('gc-templates-container');
    container.innerHTML = '';

    if (state.templates.length === 0) {
      container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #6b7280; padding: 20px;">No templates found.</p>';
      return;
    }

    state.templates.forEach(function(t) {
      var item = document.createElement('div');
      item.className = 'gc-template-item' + (state.selectedTemplateId === t.id ? ' active' : '');
      item.setAttribute('data-id', t.id);
      item.setAttribute('data-tag', t.tag);

      var img = document.createElement('img');
      img.alt = t.name;
      img.loading = 'lazy';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      img.style.display = 'block';
      // media_url is a base64 data URI embedded in JS state — set directly, no network request
      img.src = t.media_url || 'https://via.placeholder.com/300x192?text=Template';

      var overlay = document.createElement('div');
      overlay.className = 'gc-checkmark-overlay';
      overlay.innerHTML = '<div class="gc-checkmark-circle"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg></div>';

      item.appendChild(img);
      item.appendChild(overlay);

      item.addEventListener('click', function() {
        selectTemplate(t.id);
      });

      container.appendChild(item);
    });
  }

  function selectTemplate(id) {
    state.selectedTemplateId = id;
    var items = document.querySelectorAll('.gc-template-item');
    items.forEach(function(item) {
      if (parseInt(item.getAttribute('data-id')) === id) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });

    var t = state.templates.find(item => item.id === id);
    if (t) {
      state.selectedTemplateUrl = t.media_url;
      state.selectedTemplateRealUrl = t.real_media_url;
      state.selectedTemplateName = t.name;
      
      var card = document.getElementById('gc-preview-card');
      if (t.media_url) {
        // media_url is a base64 data URI — assign directly, instant display with no CORS/ngrok issues
        card.style.backgroundImage = 'linear-gradient(rgba(0,0,0,0.35),rgba(0,0,0,0.35)),url("' + t.media_url + '")';
        card.style.backgroundSize = 'cover';
        card.style.backgroundPosition = 'center';
      } else {
        card.style.backgroundImage = 'linear-gradient(135deg, #1e3a8a 0%, #0d9488 100%)';
      }
    }
  }

  function renderAmounts() {
    var select = document.getElementById('gc-field-amount');
    select.innerHTML = '';

    if (state.giftCards.length === 0) {
      var opt = document.createElement('option');
      opt.value = '';
      opt.innerText = 'No amounts available';
      select.appendChild(opt);
      return;
    }

    state.giftCards.forEach(function(g) {
      var opt = document.createElement('option');
      opt.value = g.variant_id;
      opt.innerText = '$' + parseFloat(g.amount).toFixed(2);
      select.appendChild(opt);
    });

    select.addEventListener('change', function() {
      handleAmountChange(this.value);
    });
  }

  function handleAmountChange(variantId) {
    var gc = state.giftCards.find(item => item.variant_id == variantId);
    if (gc) {
      state.selectedAmount = gc.amount;
      state.selectedVariantId = variantId;
      document.getElementById('gc-preview-amount-val').innerText = '$' + parseFloat(gc.amount).toFixed(2);
    }
  }

  function setupLivePreviewEvents() {
    var fields = [
      { id: 'gc-field-sender', previewId: 'gc-preview-from-val', prefix: 'From: ', default: 'Sender' },
      { id: 'gc-field-recipient', previewId: 'gc-preview-to-val', prefix: 'To: ', default: 'Recipient' }
    ];

    fields.forEach(function(f) {
      var el = document.getElementById(f.id);
      el.addEventListener('input', function() {
        var val = this.value.trim();
        document.getElementById(f.previewId).innerText = f.prefix + (val || f.default);
        this.classList.remove('invalid');
      });
    });

    var msgEl = document.getElementById('gc-field-message');
    msgEl.addEventListener('input', function() {
      var val = this.value;
      document.getElementById('gc-char-count').innerText = val.length;
      document.getElementById('gc-preview-msg-val').innerText = val.trim() || 'Happy gifting!';
      this.classList.remove('invalid');
    });

    var emailEl = document.getElementById('gc-field-email');
    emailEl.addEventListener('input', function() {
      this.classList.remove('invalid');
    });
  }

  function updatePreview() {
    var sender = document.getElementById('gc-field-sender').value.trim();
    var recipient = document.getElementById('gc-field-recipient').value.trim();
    var msg = document.getElementById('gc-field-message').value.trim();

    document.getElementById('gc-preview-from-val').innerText = 'From: ' + (sender || 'Sender');
    document.getElementById('gc-preview-to-val').innerText = 'To: ' + (recipient || 'Recipient');
    document.getElementById('gc-preview-msg-val').innerText = msg || 'Happy gifting!';
  }

  function setupActions() {
    // Add to Cart
    document.getElementById('gc-add-to-cart-btn').addEventListener('click', function() {
      if (!validateForm()) {
        return;
      }

      this.disabled = true;
      this.innerText = 'ADDING...';

      var props = {
        'Delivery Method': state.selectedDelivery === 'email' ? 'Send by email' : (state.selectedDelivery === 'print' ? 'Print at home' : 'Send by post'),
        'Template Name': state.selectedTemplateName || 'Default Template',
        'Sender Name': document.getElementById('gc-field-sender').value.trim(),
        'Recipient Name': document.getElementById('gc-field-recipient').value.trim()
      };

      if (state.selectedDelivery === 'email') {
        props['Recipient Email'] = document.getElementById('gc-field-email').value.trim();
      }

      if (state.selectedTemplateRealUrl) {
        props['Template Image'] = state.selectedTemplateRealUrl;
      }

      var msg = document.getElementById('gc-field-message').value.trim();
      if (msg) {
        props['Message'] = msg;
      }

      fetch('/cart/add.js', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: state.selectedVariantId,
          quantity: 1,
          properties: props
        })
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        window.location.href = '/cart';
      })
      .catch(function(err) {
        alert('Failed to add gift card to cart. Please try again.');
        var btn = document.getElementById('gc-add-to-cart-btn');
        btn.disabled = false;
        btn.innerText = 'ADD TO CART';
      });
    });

    // Preview PDF Button
    document.getElementById('gc-preview-btn').addEventListener('click', function() {
      if (!validateForm()) {
        return;
      }
      
      var sender = document.getElementById('gc-field-sender').value.trim();
      var recipient = document.getElementById('gc-field-recipient').value.trim();
      var message = document.getElementById('gc-field-message').value.trim();
      var shop = (window.Shopify && Shopify.shop) || window.location.hostname;
      
      var previewUrl = state.appUrl + '/gift-cards/storefront/preview-pdf' +
        '?amount=' + encodeURIComponent(state.selectedAmount) +
        '&sender=' + encodeURIComponent(sender) +
        '&recipient=' + encodeURIComponent(recipient) +
        '&message=' + encodeURIComponent(message) +
        '&template_id=' + (state.selectedTemplateId || '') +
        '&shop=' + encodeURIComponent(shop);
        
      window.open(previewUrl, '_blank');
    });
  }

  function validateForm() {
    var isValid = true;

    // Reset error visuals
    document.querySelectorAll('.gc-error-text').forEach(e => e.style.display = 'none');
    document.querySelectorAll('.gc-input, .gc-select').forEach(i => i.classList.remove('invalid'));

    // Amount validation
    if (!state.selectedVariantId) {
      document.getElementById('err-amount').style.display = 'block';
      document.getElementById('gc-field-amount').classList.add('invalid');
      isValid = false;
    }

    // Sender validation
    var sender = document.getElementById('gc-field-sender');
    if (!sender.value.trim()) {
      document.getElementById('err-sender').style.display = 'block';
      sender.classList.add('invalid');
      isValid = false;
    }

    // Recipient validation
    var recipient = document.getElementById('gc-field-recipient');
    if (!recipient.value.trim()) {
      document.getElementById('err-recipient').style.display = 'block';
      recipient.classList.add('invalid');
      isValid = false;
    }

    // Email validation (conditional)
    if (state.selectedDelivery === 'email') {
      var email = document.getElementById('gc-field-email');
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email.value.trim())) {
        document.getElementById('err-email').style.display = 'block';
        email.classList.add('invalid');
        isValid = false;
      }
    }

    // Scroll to first invalid field if any
    if (!isValid) {
      var firstInvalid = document.querySelector('.gc-input.invalid, .gc-select.invalid');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    return isValid;
  }

  document.addEventListener('DOMContentLoaded', init);
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

                \Illuminate\Support\Facades\Log::info('Gift Card Page Create', [
                    'status'   => $pageCreateResponse->status(),
                    'response' => $pageCreateResponse->json(),
                ]);

                if (!$pageCreateResponse->successful()) {
                    \Illuminate\Support\Facades\Log::error('Gift Card Page Creation Failed', [
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