<?php

namespace App\Services;

use App\Models\GiftCardVoucher;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class GiftCardPdfService
{
    /**
     * Generate the PDF binary data for a given voucher.
     *
     * @param GiftCardVoucher $voucher
     * @param string $shopName
     * @return string
     */
    public function generate(GiftCardVoucher $voucher, string $shopName): string
    {
        $giftCard = $voucher->giftCard;
        $template = $giftCard?->template;
        $shop = $giftCard?->shop;

        $html = $template?->body_html ?: ($shop ? $shop->getSetting('pdfContent') : '');

        if (empty($html)) {
            $html = '<table cellpadding="10" style="width:100%;text-align:center;color:#333;background:#ffffff;font-size:14px;"><tbody><tr><td style="width:25%;">&nbsp;</td><td style="width:50%;font-size:30px;border:1px solid #333;"><strong>Gift Card</strong></td><td style="width:25%;">&nbsp;</td></tr><tr><td colspan="3"><p>Hi {{card_lastname}},</p><p>You have received a <strong>{{card_price}}</strong> gift card from {{card_from}}!</p><p style="font-size:18px;margin:0;"><em>Good shopping on {{shop_name}}!</em></p></td></tr><tr><td colspan="3">{{card_image}}</td></tr><tr><td style="width:25%;">&nbsp;</td><td style="width:50%;font-size:16px;background-color:#333;color:#fff;">Your code:<br><strong>{{card_code}}</strong></td><td style="width:25%;">&nbsp;</td></tr><tr><td colspan="3"><p><strong>Message from {{card_from}}</strong></p><div>{{card_message}}</div></td></tr><tr><td colspan="3" style="font-size:1px;"></td></tr><tr><td style="width:33%;font-size:1px;">&nbsp;</td><td style="width:34%;font-size:1px;border-top:1px solid #777;">&nbsp;</td><td style="width:33%;font-size:1px;">&nbsp;</td></tr><tr><td colspan="3"><p style="font-size:16px;"><strong>To take advantage of the gift card</strong></p><p>Copy/paste your code <strong>{{card_code}}</strong> into the shopping cart before checking out.</p></td></tr></tbody></table>';
        }

        $metadata = $template?->metadata ?? [];
        $previewPrice = $voucher->remaining_balance;
        $previewCode = $voucher->code;
        $senderName = $voucher->sender_name ?: 'A Friend';
        $recipientName = $voucher->recipient_name;
        $personalMessage = $voucher->personal_message ?: '';
        $validityDate = $voucher->expires_at?->format('d.m.Y') ?: date('d.m.Y', strtotime('+1 year'));

        // Handle template image
        $imagePath = $template?->media_url ? storage_path('app/public/' . $template->media_url) : null;
        $imageHtml = '';

        if ($imagePath && file_exists($imagePath)) {
            $imageMime = mime_content_type($imagePath) ?: 'image/png';
            $gdAvailable = extension_loaded('gd');

            if ($gdAvailable) {
                $imageData = base64_encode(file_get_contents($imagePath));
                $imageHtml = '<img src="data:' . $imageMime . ';base64,' . $imageData . '" style="max-width:100%;width:300px;height:auto;display:block;margin:0 auto;" />';
            } else {
                // GD not available — attempt to convert PNG to JPEG using Python PIL
                $tempJpg = tempnam(sys_get_temp_dir(), 'gc_jpg_');
                $cmd = 'python3 -c "from PIL import Image; Image.open(' . escapeshellarg($imagePath) . ').convert(\'RGB\').save(' . escapeshellarg($tempJpg) . ', \'JPEG\')"' . ' 2>&1';
                exec($cmd, $output, $returnVar);

                if ($returnVar === 0 && file_exists($tempJpg) && filesize($tempJpg) > 0) {
                    $imageData = base64_encode(file_get_contents($tempJpg));
                    @unlink($tempJpg);
                    $imageHtml = '<img src="data:image/jpeg;base64,' . $imageData . '" style="max-width:100%;width:300px;height:auto;display:block;margin:0 auto;" />';
                } else {
                    // Fallback to a styled CSS gift card tile if Python conversion fails
                    $color1       = $metadata['custom_color_1'] ?? '#1e3a8a';
                    $templateName = $template?->name ?? 'Gift Card';
                    $formattedAmt = '$' . number_format((float) $previewPrice, 2);
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
        } else {
            // Check metadata['template_image_url']
            $templateImageUrl = $voucher->metadata['template_image_url'] ?? null;
            if ($templateImageUrl) {
                // If it is a relative path in public storage
                $relativeLocalPath = str_replace('/storage/', '', $templateImageUrl);
                $fullLocalPath = storage_path('app/public/' . $relativeLocalPath);
                if (file_exists($fullLocalPath)) {
                    $imageMime = mime_content_type($fullLocalPath) ?: 'image/png';
                    $imageData = base64_encode(file_get_contents($fullLocalPath));
                    $imageHtml = '<img src="data:' . $imageMime . ';base64,' . $imageData . '" style="max-width:100%;width:300px;height:auto;display:block;margin:0 auto;" />';
                }
            }
        }

        $replacements = [
            '{{card_lastname}}'  => htmlspecialchars($recipientName),
            '{{card_firstname}}' => '',
            '{{card_price}}'     => '$' . number_format((float) $previewPrice, 2),
            '{{card_from}}'      => htmlspecialchars($senderName),
            '{{card_code}}'      => $previewCode,
            '{{card_message}}'   => nl2br(htmlspecialchars($personalMessage)),
            '{{card_image}}'     => $imageHtml,
            '{{shop_name}}'      => htmlspecialchars($shopName),
            '{{validity_date}}'  => $validityDate,
            '{{custom_text_1}}'  => htmlspecialchars($metadata['custom_text_1'] ?? ''),
            '{{custom_text_2}}'  => htmlspecialchars($metadata['custom_text_2'] ?? ''),
            '{{custom_text_3}}'  => htmlspecialchars($metadata['custom_text_3'] ?? ''),
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><head><meta charset="UTF-8"><style>body { font-family: DejaVu Sans, sans-serif; }</style></head><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
