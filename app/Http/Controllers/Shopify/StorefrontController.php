<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\GiftCardVoucher;
use Illuminate\Http\Request;
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

        // Get only active gift cards that are associated with a template
        $activeGiftCards = \App\Models\GiftCard::where('active', true)
            ->whereNotNull('template_id')
            ->with('template')
            ->get();

        // Group by template_id
        $templates = $activeGiftCards->groupBy('template_id')->map(function ($giftCards, $templateId) {
            $template = $giftCards->first()->template;
            if (!$template) {
                return null;
            }

            // Map the gift cards associated with this template to their amounts and variant IDs
            $amounts = $giftCards->map(function ($gc) {
                return [
                    'id' => $gc->id,
                    'amount' => (float) $gc->amount,
                    'variant_id' => $gc->shopify_product_variant_id,
                ];
            })->sortBy('amount')->values();

            return [
                'id' => $template->id,
                'name' => $template->name,
                'media_url' => $template->media_url,
                'amounts' => $amounts,
            ];
        })->filter()->values();

        $content = view('shopify.storefront.customer-vouchers', [
            'vouchers'   => $vouchers,
            'templates'  => $templates,
            'isLoggedIn' => !empty($customerId),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'text/html');
    }

    public function previewPdf(Request $request): Response
    {
        // ── 1. Read all form parameters ───────────────────────────────────────
        $templateId      = $request->query('template_id');
        $rawAmount       = $request->query('amount', '0');   // numeric e.g. "45"
        $senderName      = $request->query('sender', 'Sender');
        $recipientName   = $request->query('recipient', 'Recipient');
        $personalMessage = $request->query('message', '');

        // ── 2. Resolve template & shop ────────────────────────────────────────
        $template = $templateId ? \App\Models\GiftCardTemplate::find($templateId) : null;

        $shopDomain = $request->query('shop');
        $shop = null;
        if ($shopDomain) {
            $shop = \App\Models\Shop::where('shopify_domain', $shopDomain)->first();
        }
        if (!$shop && $template) {
            $shop = \App\Models\Shop::find($template->shop_id);
        }
        if (!$shop) {
            $shop = \App\Models\Shop::first();
        }

        // ── 3. Build every replacement value ─────────────────────────────────
        $metadata     = $template?->metadata ?? [];
        $shopName     = $shop?->shopify_domain ?? 'My Store';
        $formattedAmt = '$' . number_format((float) $rawAmount, 2);
        $validDate    = date('d.m.Y', strtotime('+1 year'));
        $giftCode     = 'XXXX-XXXX-XXXX-XXXX';

        // ── 4. Build template visual (CSS card — no GD/image extension needed) ─
        $color1       = $metadata['custom_color_1'] ?? '#1e3a8a';
        $color2       = $metadata['custom_color_2'] ?? '#0d9488';
        $templateName = $template?->name ?? 'Gift Card';

        // Try to embed as JPEG (Dompdf parses JPEG without GD).
        // PNG requires GD, so we skip if mime is PNG and GD is absent.
        $imagePath = $template?->media_url
            ? storage_path('app/public/' . $template->media_url)
            : null;

        $imageHtml = '';
        if ($imagePath && file_exists($imagePath)) {
            $imageMime = mime_content_type($imagePath) ?: 'image/png';
            $gdAvailable = extension_loaded('gd');

            if ($gdAvailable) {
                // Encode directly as base64
                $imageData = base64_encode(file_get_contents($imagePath));
                $imageHtml = '<img src="data:' . $imageMime . ';base64,' . $imageData
                           . '" style="max-width:300px;width:300px;height:auto;display:block;margin:0 auto;" />';
            } else {
                // GD not available — attempt to convert PNG to JPEG using Python PIL
                // since Dompdf's CPDF class decodes JPEGs in pure PHP without GD.
                $tempJpg = tempnam(sys_get_temp_dir(), 'gc_jpg_');
                $cmd = 'python3 -c "from PIL import Image; Image.open(' . escapeshellarg($imagePath) . ').convert(\'RGB\').save(' . escapeshellarg($tempJpg) . ', \'JPEG\')"' . ' 2>&1';
                exec($cmd, $output, $returnVar);

                if ($returnVar === 0 && file_exists($tempJpg) && filesize($tempJpg) > 0) {
                    $imageData = base64_encode(file_get_contents($tempJpg));
                    @unlink($tempJpg);
                    $imageHtml = '<img src="data:image/jpeg;base64,' . $imageData
                               . '" style="max-width:300px;width:300px;height:auto;display:block;margin:0 auto;" />';
                } else {
                    // Fallback to a styled CSS gift card tile if Python conversion fails
                    $imageHtml = '<div style="'
                        . 'width:300px;height:192px;margin:12px auto;border-radius:12px;'
                        . 'background:' . $color1 . ';color:#fff;'
                        . 'display:table;text-align:center;font-family:DejaVu Sans,sans-serif;">'
                        . '<div style="display:table-cell;vertical-align:middle;">'
                        . '<div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:0.7;">Gift Card</div>'
                        . '<div style="font-size:28px;font-weight:bold;margin:8px 0;">' . htmlspecialchars($formattedAmt) . '</div>'
                        . '<div style="font-size:12px;letter-spacing:2px;opacity:0.8;">' . htmlspecialchars($templateName) . '</div>'
                        . '</div></div>';
                }
            }
        }

        // ── 5. Load pdfContent template and replace all placeholders ──────────
        $html = $template?->body_html ?: ($shop ? $shop->getSetting('pdfContent') : '');
        if (empty($html)) {
            $html = '<p>Hi {{card_lastname}},</p>'
                  . '<p>You have received a <strong>{{card_price}}</strong> gift card from {{card_from}}!</p>'
                  . '<p><em>Good shopping on {{shop_name}}!</em></p>'
                  . '{{card_image}}'
                  . '<p><strong>Your code: {{card_code}}</strong></p>'
                  . '<p><strong>Message from {{card_from}}</strong></p>'
                  . '<div>{{card_message}}</div>';
        }

        $html = str_replace(
            [
                '{{card_lastname}}',
                '{{card_firstname}}',
                '{{card_price}}',
                '{{card_from}}',
                '{{card_code}}',
                '{{card_message}}',
                '{{card_image}}',
                '{{shop_name}}',
                '{{validity_date}}',
                '{{custom_text_1}}',
                '{{custom_text_2}}',
                '{{custom_text_3}}',
            ],
            [
                htmlspecialchars($recipientName),
                '',
                htmlspecialchars($formattedAmt),
                htmlspecialchars($senderName),
                $giftCode,
                nl2br(htmlspecialchars($personalMessage ?: 'No message provided.')),
                $imageHtml,
                htmlspecialchars($shopName),
                $validDate,
                htmlspecialchars($metadata['custom_text_1'] ?? ''),
                htmlspecialchars($metadata['custom_text_2'] ?? ''),
                htmlspecialchars($metadata['custom_text_3'] ?? ''),
            ],
            $html
        );

        // ── 6. Wrap in full HTML document ─────────────────────────────────────
        $fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                  . '<style>'
                  . 'body{font-family:DejaVu Sans,Helvetica,Arial,sans-serif;font-size:14px;color:#333;background:#fff;}'
                  . 'table{border-collapse:collapse;}'
                  . 'img{display:block;margin:0 auto;}'
                  . '</style>'
                  . '</head><body>'
                  . $html
                  . '</body></html>';

        // ── 7. Render PDF ─────────────────────────────────────────────────────
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($fullHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-preview.pdf"',
        ]);
    }

    public function proxyImage(Request $request): Response
    {
        $path = $request->query('path');
        if (!$path) {
            abort(404);
        }

        // Sanitize path to prevent directory traversal
        $path = str_replace(['../', '..\\'], '', $path);

        $localPath = storage_path('app/public/' . $path);
        if (!file_exists($localPath) || !is_file($localPath)) {
            abort(404);
        }

        $file = file_get_contents($localPath);
        $type = mime_content_type($localPath);

        return response($file, 200)
            ->header('Content-Type', $type)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function openGiftCard(string $secureToken): Response
    {
        $voucher = GiftCardVoucher::where('metadata->secure_token', $secureToken)->firstOrFail();
        $giftCard = $voucher->giftCard;
        $template = $giftCard?->template;
        $shop = $giftCard?->shop;

        // Resolve template image
        $templateImagePath = $voucher->metadata['template_image_url'] ?? null;
        $templateMediaUrl  = null;

        if ($template?->media_url) {
            $templateMediaUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($template->media_url);
        } elseif ($templateImagePath) {
            if (filter_var($templateImagePath, FILTER_VALIDATE_URL)) {
                $templateMediaUrl = $templateImagePath;
            } else {
                $templateMediaUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($templateImagePath);
            }
        }

        $shopName = $shop ? $shop->shopify_domain : 'Our Store';

        return response(view('shopify.storefront.open-gift-card', [
            'voucher'          => $voucher,
            'template'         => $template,
            'templateMediaUrl' => $templateMediaUrl,
            'shop'             => $shop,
            'shopName'         => $shopName,
            'secureToken'      => $secureToken,
        ])->render(), 200)->header('Content-Type', 'text/html');
    }

    public function downloadPdf(string $secureToken): Response
    {
        $voucher = GiftCardVoucher::where('metadata->secure_token', $secureToken)->firstOrFail();
        $shop = $voucher->giftCard?->shop;
        $shopName = $shop ? $shop->shopify_domain : 'Our Store';

        $pdfService = app(\App\Services\GiftCardPdfService::class);
        $pdfData = $pdfService->generate($voucher, $shopName);

        return response($pdfData, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="GiftCard-' . $voucher->code . '.pdf"',
        ]);
    }

    public function validateGiftCard(Request $request): Response
    {
        $code = strtoupper(trim($request->input('code', '')));
        $cartTotal = (float) $request->input('cart_total', 0);
        $shopDomain = $request->query('shop') ?: $request->input('shop');

        if (empty($code)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a gift card code.'
            ], 400);
        }

        $shop = \App\Models\Shop::where('shopify_domain', $shopDomain)->first();
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.'
            ], 400);
        }

        $voucher = GiftCardVoucher::where('code', $code)
            ->whereIn('status', ['unused', 'delivered', 'partially_used'])
            ->whereDate('expires_at', '>=', now()->format('Y-m-d'))
            ->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid, expired, or fully used gift card.'
            ], 400);
        }

        $today = now()->format('Y-m-d');
        if (\Carbon\Carbon::parse($voucher->scheduled_send_date)->format('Y-m-d') > $today) {
            return response()->json([
                'success' => false,
                'message' => 'This gift card is not active yet.'
            ], 400);
        }

        if ($voucher->remaining_balance <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This gift card has no remaining balance.'
            ], 400);
        }

        // Calculate discount amount
        $discountAmount = min($cartTotal, (float) $voucher->remaining_balance);
        if ($discountAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cart total must be greater than 0.'
            ], 400);
        }

        // Generate temporary Shopify PriceRule & Discount Code
        $shopifyService = app(\App\Services\Shopify\ShopifyService::class);
        
        $tempCode = 'GC-TEMP-' . $voucher->code . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Create Shopify Price Rule
        $priceRulePayload = [
            'price_rule' => [
                'title' => $tempCode,
                'target_type' => 'line_item',
                'target_selection' => 'all',
                'allocation_method' => 'across',
                'value_type' => 'fixed_amount',
                'value' => '-' . number_format($discountAmount, 2, '.', ''),
                'customer_selection' => 'all',
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->addDay()->toIso8601String(), // Expires in 24 hours
                'usage_limit' => 1,
            ]
        ];

        try {
            $prResponse = $shopifyService->api($shop, 'POST', 'price_rules.json', $priceRulePayload);
            if (!$prResponse->successful()) {
                \Illuminate\Support\Facades\Log::error('ValidateGiftCard: Shopify Price Rule creation failed', ['response' => $prResponse->body()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Could not apply gift card on Shopify. Please try again.'
                ], 500);
            }

            $prData = $prResponse->json();
            $priceRuleId = $prData['price_rule']['id'];

            // Create Shopify Discount Code
            $dcPayload = [
                'discount_code' => [
                    'code' => $tempCode
                ]
            ];
            $dcResponse = $shopifyService->api($shop, 'POST', "price_rules/{$priceRuleId}/discount_codes.json", $dcPayload);
            if (!$dcResponse->successful()) {
                \Illuminate\Support\Facades\Log::error('ValidateGiftCard: Shopify Discount Code creation failed', ['response' => $dcResponse->body()]);
                // Cleanup price rule
                $shopifyService->api($shop, 'DELETE', "price_rules/{$priceRuleId}.json");
                return response()->json([
                    'success' => false,
                    'message' => 'Could not generate discount code. Please try again.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'discount_code' => $tempCode,
                'price_rule_id' => $priceRuleId,
                'discount_amount' => $discountAmount,
                'original_balance' => (float) $voucher->remaining_balance,
                'remaining_balance' => max(0.0, (float) $voucher->remaining_balance - $discountAmount),
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ValidateGiftCard Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during gift card application.'
            ], 500);
        }
    }

    public function removeGiftCard(Request $request): Response
    {
        $priceRuleId = $request->input('price_rule_id');
        $shopDomain = $request->query('shop') ?: $request->input('shop');

        if (!$priceRuleId) {
            return response()->json(['success' => true]);
        }

        $shop = \App\Models\Shop::where('shopify_domain', $shopDomain)->first();
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 400);
        }

        $shopifyService = app(\App\Services\Shopify\ShopifyService::class);
        try {
            $shopifyService->api($shop, 'DELETE', "price_rules/{$priceRuleId}.json");
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('RemoveGiftCard Exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
