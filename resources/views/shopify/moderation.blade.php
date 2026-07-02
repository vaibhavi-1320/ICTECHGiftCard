@include('shopify.layout-start', ['title' => 'Moderation Tool', 'subtitle' => 'Audit and moderate gift card vouchers', 'shopDomain' => $shopDomain])

    <ui-title-bar title="Moderation Tool"></ui-title-bar>

    <div id="moderation-tool-react"
         data-shop-domain="{{ $shopDomain }}"
         data-host="{{ request('host') }}"
         data-vouchers-url="{{ route('shopify.moderation.search', [], false) }}"
         data-resend-email-url="{{ route('shopify.moderation.resend-email', [], false) }}"
         data-adjust-balance-url="{{ route('shopify.moderation.adjust-balance', [], false) }}"
         data-revoke-url="{{ route('shopify.moderation.revoke', [], false) }}"
    ></div>

@include('shopify.layout-end')
