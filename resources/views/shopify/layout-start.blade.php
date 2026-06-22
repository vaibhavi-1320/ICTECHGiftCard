<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ICTECH Gift Card' }}</title>
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}" />
    <link rel="stylesheet" href="https://unpkg.com/@shopify/polaris@12.0.0/build/esm/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    @vite(['resources/js/app.js'])
    <style>
        body { 
            margin: 0; 
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; 
            background: var(--p-color-bg-app, #f6f6f7); 
            color: var(--p-color-text, #202223); 
            -webkit-font-smoothing: antialiased;
        }
        .notice-banner {
            padding: 12px 16px;
            background-color: var(--p-color-bg-surface-success, #e7f5ef);
            border: 1px solid var(--p-color-border-success, #a3e0c4);
            border-radius: 6px;
            color: var(--p-color-text-success, #1e5e41);
            margin-bottom: 20px;
            font-size: 14px;
        }
        /* Custom helper classes for grid/form elements matching Polaris */
        .polaris-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        @media (max-width: 768px) {
            .polaris-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <ui-nav-menu>
        <a href="{{ route('shopify.dashboard', request()->query(), false) }}" rel="home">ICTECHGiftCard</a>
        <a href="{{ route('shopify.dashboard', request()->query(), false) }}">Dashboard</a>
        <a href="{{ route('shopify.gift-cards.index', request()->query(), false) }}">Gift Cards</a>
        <a href="{{ route('shopify.templates.index', request()->query(), false) }}">Templates</a>
        <a href="{{ route('shopify.settings.edit', request()->query(), false) }}">Settings</a>
    </ui-nav-menu>

    <div class="Polaris-Page">
        <div class="Polaris-Page__Content">
            @if (session('status'))
                <div class="notice-banner">
                    {{ session('status') }}
                </div>
            @endif
