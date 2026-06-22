@include('shopify.layout-start', ['title' => $giftCard->exists ? 'Edit Gift Card' : 'Create Gift Card', 'shopDomain' => $shopDomain])
    <ui-title-bar title="{{ $giftCard->exists ? 'Edit Gift Card' : 'Create Gift Card' }}">
        <button variant="primary" onclick="document.getElementById('giftcard-form')?.requestSubmit()">Save</button>
        <button onclick="window.location.href='{{ route('shopify.gift-cards.index', request()->query(), false) }}'">Cancel</button>
    </ui-title-bar>

    <form id="giftcard-form" method="POST" enctype="multipart/form-data" action="{{ $giftCard->exists ? route('shopify.gift-cards.update', array_merge(request()->query(), ['giftCard' => $giftCard->id]), false) : route('shopify.gift-cards.store', request()->query(), false) }}">
        @csrf
        @if ($giftCard->exists)
            @method('PUT')
        @endif

        <div
            id="giftcard-form-react"
            data-config="{{ e(json_encode([
                'csrfToken' => csrf_token(),
                'title' => $giftCard->exists ? 'Edit Gift Card Details' : 'Create Gift Card Details',
                'name' => old('name', $giftCard->name),
                'amount' => old('amount', $giftCard->amount),
                'codePrefix' => old('code_prefix', $giftCard->code_prefix),
                'validityDays' => old('validity_days', $giftCard->validity_days ?: 365),
                'templateId' => old('template_id', $giftCard->template_id),
                'active' => old('active', $giftCard->active ?? true),
                'templates' => $templates->map(fn ($template) => ['id' => $template->id, 'name' => $template->name])->values(),
            ])) }}"
        ></div>
    </form>
@include('shopify.layout-end')
