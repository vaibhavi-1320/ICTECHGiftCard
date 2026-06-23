<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTemplate;
use App\Models\Shop;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GiftCardTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->resolveShop($request->string('shop')->toString());

        $templates = Schema::hasTable('gift_card_templates')
            ? GiftCardTemplate::query()
                ->when($shop?->id, fn ($query, $shopId) => $query->where('shop_id', $shopId))
                ->latest()
                ->get()
                ->map(fn (GiftCardTemplate $template) => $this->attachTemplateImageUrl($template))
            : collect();

        return view('shopify.templates.index', [
            'shop' => $shop,
            'shopDomain' => $request->string('shop')->toString(),
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): View
    {
        $shop = $this->resolveShop($request->string('shop')->toString());

        return view('shopify.templates.form', [
            'shop' => $shop,
            'shopDomain' => $request->string('shop')->toString(),
            'template' => new GiftCardTemplate(),
        ]);
    }

    public function edit(Request $request, int $templateId): View
    {
        $template = $this->findTemplate($templateId);

        if ($template === null) {
            abort(404);
        }

        return view('shopify.templates.form', [
            'shop' => $this->resolveShop($request->string('shop')->toString()),
            'shopDomain' => $request->string('shop')->toString(),
            'template' => $this->attachTemplateImageUrl($template),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = new GiftCardTemplate();
        $template->shop_id = $this->resolveShop($request->string('shop')->toString())?->id;
        $this->fillTemplateFromRequest($request, $template);
        $template->save();

        return redirect()->route('shopify.templates.index', $request->query())->with('status', 'Template created.');
    }

    public function update(Request $request, int $templateId): RedirectResponse
    {
        $template = $this->findTemplate($templateId);

        if ($template === null) {
            abort(404);
        }

        $this->fillTemplateFromRequest($request, $template);
        $template->save();

        return redirect()->route('shopify.templates.index', $request->query())->with('status', 'Template updated.');
    }

    private function fillTemplateFromRequest(Request $request, GiftCardTemplate $template): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:100'],
            'media_upload' => ['nullable', 'file', 'image', 'max:5120'],
            'active' => ['nullable', 'boolean'],
            'body_html' => ['nullable', 'string'],
            'preview_title' => ['nullable', 'string', 'max:100'],
            'preview_price' => ['nullable', 'string', 'max:50'],
            'preview_code' => ['nullable', 'string', 'max:50'],
            'custom_text_1' => ['nullable', 'string', 'max:255'],
            'custom_text_2' => ['nullable', 'string', 'max:255'],
            'custom_text_3' => ['nullable', 'string', 'max:255'],
            'custom_color_1' => ['nullable', 'string', 'max:20'],
            'custom_color_2' => ['nullable', 'string', 'max:20'],
            'custom_color_3' => ['nullable', 'string', 'max:20'],
            'pdf_only_image' => ['nullable', 'boolean'],
        ]);

        $template->fill([
            'name' => $data['name'],
            'tag' => $data['tag'] ?? '',
            'active' => (bool) ($data['active'] ?? false),
            'body_html' => $data['body_html'] ?? null,
        ]);

        if ($request->hasFile('media_upload')) {
            $template->media_url = $request->file('media_upload')->store('gift-card-templates', 'public');
        }

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $existingMetadata = json_decode((string) $template->getRawOriginal('metadata'), true);
        $existingMetadata = is_array($existingMetadata) ? $existingMetadata : [];
        $metadata = array_merge($existingMetadata, $metadata);
        $metadata = array_merge($metadata, [
            'preview_title' => array_key_exists('preview_title', $data) ? ($data['preview_title'] ?: 'HAPPY BIRTHDAY') : ($metadata['preview_title'] ?? 'HAPPY BIRTHDAY'),
            'preview_price' => array_key_exists('preview_price', $data) ? ($data['preview_price'] ?: '100') : ($metadata['preview_price'] ?? '100'),
            'preview_code' => array_key_exists('preview_code', $data) ? ($data['preview_code'] ?: 'XXXXXXXXXX') : ($metadata['preview_code'] ?? 'XXXXXXXXXX'),
            'custom_text_1' => array_key_exists('custom_text_1', $data) ? ($data['custom_text_1'] ?: 'PrestaShop') : ($metadata['custom_text_1'] ?? 'PrestaShop'),
            'custom_text_2' => array_key_exists('custom_text_2', $data) ? ($data['custom_text_2'] ?: 'HAPPY') : ($metadata['custom_text_2'] ?? 'HAPPY'),
            'custom_text_3' => array_key_exists('custom_text_3', $data) ? ($data['custom_text_3'] ?: 'BIRTHDAY') : ($metadata['custom_text_3'] ?? 'BIRTHDAY'),
            'custom_color_1' => array_key_exists('custom_color_1', $data) ? ($data['custom_color_1'] ?: '#ff6a3d') : ($metadata['custom_color_1'] ?? '#ff6a3d'),
            'custom_color_2' => array_key_exists('custom_color_2', $data) ? ($data['custom_color_2'] ?: '#ff6a3d') : ($metadata['custom_color_2'] ?? '#ff6a3d'),
            'custom_color_3' => array_key_exists('custom_color_3', $data) ? ($data['custom_color_3'] ?: '#ff6a3d') : ($metadata['custom_color_3'] ?? '#ff6a3d'),
            'pdf_only_image' => array_key_exists('pdf_only_image', $data) ? (bool) $data['pdf_only_image'] : (bool) ($metadata['pdf_only_image'] ?? false),
        ]);

        $template->metadata = $metadata;
    }

    private function resolveShop(string $shopDomain): ?Shop
    {
        if ($shopDomain === '' || ! Schema::hasTable('shops')) {
            return null;
        }

        return Shop::query()->where('shopify_domain', $shopDomain)->first();
    }

    private function findTemplate(int $templateId): ?GiftCardTemplate
    {
        if (! Schema::hasTable('gift_card_templates')) {
            return null;
        }

        return GiftCardTemplate::query()->find($templateId);
    }

    private function attachTemplateImageUrl(GiftCardTemplate $template): GiftCardTemplate
    {
        $template->setAttribute(
            'resolved_image_url',
            $template->media_url ? Storage::disk('public')->url($template->media_url) : null
        );

        return $template;
    }

    public function previewPdf(Request $request, int $templateId)
    {
        $template = $this->findTemplate($templateId);
        if (!$template) {
            abort(404);
        }

        $shop = $this->resolveShop($request->string('shop')->toString());
        $html = $template->body_html ?: ($shop ? $shop->getSetting('pdfContent') : '');

        if (empty($html)) {
            return response('No PDF template content configured.', 400);
        }

        $metadata = $template->metadata ?? [];
        $previewTitle = $metadata['preview_title'] ?? 'HAPPY BIRTHDAY';
        $previewPrice = $metadata['preview_price'] ?? '100.00';
        $previewCode = $metadata['preview_code'] ?? 'XXXXXXXXXX';
        $senderName = $metadata['custom_text_1'] ?? 'Jane Smith';
        $recipientName = 'John Doe';
        $personalMessage = 'Happy Birthday! Enjoy your gift.';

        $templateMediaUrl = $template->media_url ? url('/storage/' . $template->media_url) : null;
        $imageHtml = $templateMediaUrl
            ? '<img src="' . $templateMediaUrl . '" style="max-width:300px;height:auto;" />'
            : '<div style="width:300px;height:192px;border:1px solid #ccc;background:#eee;text-align:center;line-height:192px;">[Gift Card Image]</div>';

        $replacements = [
            '{{card_lastname}}'  => $recipientName,
            '{{card_firstname}}' => 'John',
            '{{card_price}}'     => '$' . number_format((float) $previewPrice, 2),
            '{{card_from}}'      => $senderName,
            '{{card_code}}'      => $previewCode,
            '{{card_message}}'   => $personalMessage,
            '{{card_image}}'     => $imageHtml,
            '{{shop_name}}'      => $shop ? $shop->shopify_domain : 'My Store',
            '{{validity_date}}'  => date('d.m.Y', strtotime('+1 year')),
            '{{custom_text_1}}'  => $metadata['custom_text_1'] ?? '',
            '{{custom_text_2}}'  => $metadata['custom_text_2'] ?? '',
            '{{custom_text_3}}'  => $metadata['custom_text_3'] ?? '',
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><head><style>body { font-family: DejaVu Sans, sans-serif; }</style></head><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-template-preview.pdf"',
        ]);
    }
}
