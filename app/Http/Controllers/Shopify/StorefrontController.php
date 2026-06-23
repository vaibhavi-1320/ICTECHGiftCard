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

        $activeGiftCards = \App\Models\GiftCard::where('active', true)
            ->latest()
            ->get();

        $content = view('shopify.storefront.customer-vouchers', [
            'vouchers'       => $vouchers,
            'activeGiftCards' => $activeGiftCards,
            'isLoggedIn'     => !empty($customerId),
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
}
