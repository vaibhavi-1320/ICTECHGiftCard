@include('shopify.layout-start', ['title' => 'Templates', 'shopDomain' => $shopDomain])
    <ui-title-bar title="Templates">
        <button variant="primary" onclick="window.location.href='{{ route('shopify.templates.create', request()->query(), false) }}'">Create Template</button>
    </ui-title-bar>

    <div id="templates-list-react"
        data-config="{{ e(json_encode([
            'createUrl' => route('shopify.templates.create', request()->query(), false),
            'rows' => $templates->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'tag' => $template->tag,
                'active' => (bool) $template->active,
                'imageUrl' => $template->resolved_image_url ?? ($template->media_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($template->media_url) : null),
                'editUrl' => route('shopify.templates.edit', array_merge(request()->query(), ['templateId' => $template->id]), false),
            ])->values(),
        ])) }}"
        data-shop-domain="{{ $shopDomain }}">
    </div>
@include('shopify.layout-end')
