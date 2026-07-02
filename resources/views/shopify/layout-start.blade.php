<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ICTECH Gift Card' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            opacity: 1;
            transition: opacity 0.5s ease, margin-bottom 0.5s ease, padding 0.5s ease, height 0.5s ease, border-width 0.5s ease;
            overflow: hidden;
            box-sizing: border-box;
        }
        .notice-banner.fade-out {
            opacity: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            height: 0 !important;
            border-top-width: 0 !important;
            border-bottom-width: 0 !important;
            border-left-width: 0 !important;
            border-right-width: 0 !important;
        }
        .error-banner {
            padding: 12px 16px;
            background-color: var(--p-color-bg-surface-critical, #fedad7);
            border: 1px solid var(--p-color-border-critical, #f8a19b);
            border-radius: 6px;
            color: var(--p-color-text-critical, #8a1715);
            margin-bottom: 20px;
            font-size: 14px;
            opacity: 1;
            transition: opacity 0.5s ease, margin-bottom 0.5s ease, padding 0.5s ease, height 0.5s ease, border-width 0.5s ease;
            overflow: hidden;
            box-sizing: border-box;
        }
        .error-banner.fade-out {
            opacity: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            height: 0 !important;
            border-top-width: 0 !important;
            border-bottom-width: 0 !important;
            border-left-width: 0 !important;
            border-right-width: 0 !important;
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
        
        /* Save loader overlay styling */
        .save-loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: all;
        }
        .save-loader-overlay.active {
            opacity: 1;
        }
        .save-loader-container {
            background: #ffffff;
            padding: 32px 48px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .save-loader-overlay.active .save-loader-container {
            transform: scale(1);
        }
        .save-loader-spinner {
            width: 40px;
            height: 40px;
            border: 3.5px solid rgba(0, 128, 96, 0.15); /* Polaris primary green base */
            border-top-color: #008060; /* Polaris primary green active */
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .save-loader-text {
            font-size: 15px;
            font-weight: 500;
            color: #202223;
            font-family: 'Inter', sans-serif;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="save-loader-overlay" id="save-loader-overlay" style="display: none;">
        <div class="save-loader-container">
            <div class="save-loader-spinner"></div>
            <div class="save-loader-text">Saving your changes...</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const banners = document.querySelectorAll('.notice-banner, .error-banner');
            banners.forEach(banner => {
                setTimeout(() => {
                    banner.style.height = banner.offsetHeight + 'px';
                    void banner.offsetHeight; // Force reflow
                    banner.classList.add('fade-out');
                    setTimeout(() => {
                        banner.remove();
                    }, 500);
                }, 4000); // 4 seconds for error visibility
            });
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form && form.getAttribute('method')?.toUpperCase() === 'POST') {
                if (e.defaultPrevented) {
                    return;
                }
                
                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return;
                }
                form.dataset.submitting = 'true';
                
                const overlay = document.getElementById('save-loader-overlay');
                if (overlay) {
                    overlay.style.display = 'flex';
                    void overlay.offsetWidth;
                    overlay.classList.add('active');
                }
            }
        }, false);
    </script>

    <ui-nav-menu>
        <a href="{{ route('shopify.dashboard', request()->query(), false) }}" rel="home">ICTECHGiftCard</a>
        <a href="{{ route('shopify.dashboard', request()->query(), false) }}">Dashboard</a>
        <a href="{{ route('shopify.gift-cards.index', request()->query(), false) }}">Gift Cards</a>
        <a href="{{ route('shopify.templates.index', request()->query(), false) }}">Templates</a>
        <a href="{{ route('shopify.settings.edit', request()->query(), false) }}">Settings</a>
        <a href="{{ route('shopify.moderation.index', request()->query(), false) }}">Moderation Tool</a>
    </ui-nav-menu>

    <div class="Polaris-Page">
        <div class="Polaris-Page__Content">
            @if (session('status'))
                <div class="notice-banner">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="error-banner">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="error-banner">
                    {{ $errors->first() }}
                </div>
            @endif
