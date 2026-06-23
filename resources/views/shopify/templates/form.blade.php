@php
    $isEdit = $template->exists;
    $metadata = is_array($template->metadata) ? $template->metadata : [];
    $templateMediaUrl = $template->resolved_image_url ?? ($template->media_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($template->media_url) : null);
@endphp

@include('shopify.layout-start', [
    'title' => $isEdit ? 'Edit Template' : 'Create Template',
    'shopDomain' => $shopDomain,
])

    <ui-title-bar title="{{ $isEdit ? 'Edit Template' : 'Create Template' }}">
        <button variant="primary" onclick="document.getElementById('template-form')?.requestSubmit()">Save</button>
        @if ($isEdit)
            <button onclick="window.open('{{ route('shopify.templates.preview-pdf', array_merge(request()->query(), ['templateId' => $template->id]), false) }}', '_blank')">Preview PDF</button>
        @endif
        <button onclick="window.location.href='{{ route('shopify.templates.index', request()->query(), false) }}'">Cancel</button>
    </ui-title-bar>

    <div
        id="template-form-react"
        data-config="{{ e(json_encode([
            'csrfToken' => csrf_token(),
            'formAction' => $isEdit
                ? route('shopify.templates.update', array_merge(request()->query(), ['templateId' => $template->id]), false)
                : route('shopify.templates.store', request()->query(), false),
            'methodField' => $isEdit ? 'PUT' : '',
            'name' => old('name', $template->name),
            'tag' => old('tag', $template->tag),
            'active' => old('active', $template->active ?? true),
            'bodyHtml' => old('body_html', $template->body_html),
            'previewTitle' => old('preview_title', $metadata['preview_title'] ?? 'HAPPY BIRTHDAY'),
            'previewPrice' => old('preview_price', $metadata['preview_price'] ?? '100'),
            'previewCode' => old('preview_code', $metadata['preview_code'] ?? 'XXXXXXXXXX'),
            'customText1' => old('custom_text_1', $metadata['custom_text_1'] ?? 'PrestaShop'),
            'customText2' => old('custom_text_2', $metadata['custom_text_2'] ?? 'HAPPY'),
            'customText3' => old('custom_text_3', $metadata['custom_text_3'] ?? 'BIRTHDAY'),
            'customColor1' => old('custom_color_1', $metadata['custom_color_1'] ?? '#ff6a3d'),
            'customColor2' => old('custom_color_2', $metadata['custom_color_2'] ?? '#ff6a3d'),
            'customColor3' => old('custom_color_3', $metadata['custom_color_3'] ?? '#ff6a3d'),
            'pdfOnlyImage' => old('pdf_only_image', $metadata['pdf_only_image'] ?? false),
            'templateMediaUrl' => $templateMediaUrl,
            'shopDomain' => $shopDomain,
            'previewHtml' => $shop ? $shop->getSetting('pdfContent') : '',
            'previewMessage' => 'Happy Birthday! Enjoy your gift.',
            'validityDate' => date('d.m.Y', strtotime('+1 year')),
        ])) }}"
    ></div>

@include('shopify.layout-end')
