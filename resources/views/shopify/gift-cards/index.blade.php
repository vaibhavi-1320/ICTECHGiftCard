@include('shopify.layout-start', ['title' => 'Gift Cards', 'shopDomain' => $shopDomain])
    <ui-title-bar title="Gift Cards">
        <button variant="primary" onclick="window.location.href='{{ route('shopify.gift-cards.create', request()->query(), false) }}'">Create Gift Card</button>
    </ui-title-bar>

    <div
        id="giftcards-list-react"
        data-config="{{ e(json_encode([
            'csrfToken' => csrf_token(),
            'createUrl' => route('shopify.gift-cards.create', request()->query(), false),
            'rows' => $giftCards->map(fn ($giftCard) => [
                'id' => $giftCard->id,
                'name' => $giftCard->name,
                'amount' => (float) $giftCard->amount,
                'codePrefix' => $giftCard->code_prefix,
                'validityDays' => $giftCard->validity_days ?: 365,
                'active' => (bool) $giftCard->active,
                'editUrl' => route('shopify.gift-cards.edit', array_merge(request()->query(), ['giftCard' => $giftCard->id]), false),
                'deleteUrl' => route('shopify.gift-cards.destroy', array_merge(request()->query(), ['giftCard' => $giftCard->id]), false),
            ])->values(),
        ])) }}"
    ></div>
@include('shopify.layout-end')
