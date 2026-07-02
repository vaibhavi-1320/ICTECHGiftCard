<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Your Gift Card - {{ $shopName }}</title>
    <!-- Google Fonts: Outfit (modern sans-serif) & Great Vibes (premium calligraphy) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #008060;
            --primary-hover: #004b36;
            --bg-page: #f4f6f8;
            --text-main: #202223;
            --text-muted: #6d7175;
            --shadow-premium: 0 20px 40px rgba(0, 0, 0, 0.06);
            --border-radius: 16px;
        }

        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(circle at center, #231215 0%, #0f0709 100%);
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        .page-container {
            width: 100%;
            max-width: 650px;
            padding: 24px;
            box-sizing: border-box;
            text-align: center;
            display: none;
            opacity: 0;
            transition: opacity 0.8s ease;
        }

        .logo-container {
            margin-bottom: 24px;
        }

        .shop-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--primary);
            text-decoration: none;
        }

        .giftcard-container {
            background-color: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-premium);
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 40px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card visual container */
        .card-visual-wrapper {
            margin-bottom: 32px;
            perspective: 1000px;
        }

        .card-visual {
            width: 100%;
            max-width: 440px;
            aspect-ratio: 1.6 / 1;
            margin: 0 auto;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #008060 0%, #004b36 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 28px;
            box-sizing: border-box;
            color: #ffffff;
            text-align: left;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            transform-style: preserve-3d;
        }

        .card-visual:hover {
            transform: translateY(-8px) rotateX(4deg) rotateY(-4deg);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
        }

        .card-image-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.25);
            z-index: 2;
        }

        .card-content {
            position: relative;
            z-index: 3;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .card-header-tag {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .card-amount {
            font-size: 38px;
            font-weight: 800;
            margin: 0;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .card-holder {
            font-size: 13px;
            font-weight: 500;
            opacity: 0.9;
        }

        .card-brand-label {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.2px;
        }

        /* Message block */
        .personal-message-box {
            background-color: #f9fafb;
            border-left: 4px solid var(--primary);
            padding: 20px 24px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 32px;
            text-align: left;
            box-sizing: border-box;
        }

        .message-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .message-body {
            font-size: 15px;
            font-style: italic;
            line-height: 1.6;
            color: #454f5b;
            margin: 0;
        }

        /* Voucher Details UI */
        .code-display-box {
            background-color: #f4f6f8;
            border: 1px dashed rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .code-label-group {
            text-align: left;
        }

        .code-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .code-text {
            font-family: monospace;
            font-size: 19px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 1px;
            margin: 4px 0 0 0;
        }

        .btn-copy {
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-copy:hover {
            background-color: var(--primary-hover);
        }

        /* Main CTA Actions */
        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-primary {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--primary);
            color: #ffffff;
            text-decoration: none;
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(0, 128, 96, 0.15);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #ffffff;
            color: var(--text-main);
            text-decoration: none;
            padding: 16px 24px;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: #fafbfb;
            border-color: rgba(0, 0, 0, 0.25);
        }

        /* Info list */
        .info-list {
            margin-top: 32px;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .info-list strong {
            color: var(--text-main);
        }

        /* Premium Card Design Styles */
        .premium-card {
            background-color: #fdfbf7;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45);
            padding: 48px 40px;
            position: relative;
            z-index: 10;
            margin-top: 32px;
            border: 6px double rgba(212, 175, 55, 0.2);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Red Bow at the Top of the Card */
        .premium-card-bow {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 48px;
            z-index: 20;
        }

        .bow-loop-l, .bow-loop-r {
            position: absolute;
            top: 0;
            width: 36px;
            height: 30px;
            background: linear-gradient(135deg, #e63946 0%, #d62828 100%);
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .bow-loop-l {
            left: 5px;
            transform: rotate(-15deg);
        }

        .bow-loop-r {
            right: 5px;
            transform: rotate(15deg);
        }

        .bow-loop-l::after, .bow-loop-r::after {
            content: '';
            position: absolute;
            top: 6px;
            width: 14px;
            height: 12px;
            background: #9d0208;
            border-radius: 50%;
        }

        .bow-loop-l::after { left: 12px; }
        .bow-loop-r::after { right: 12px; }

        .bow-knot {
            position: absolute;
            top: 8px;
            left: 31px;
            width: 18px;
            height: 18px;
            background: linear-gradient(135deg, #f94144 0%, #d62828 100%);
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 5;
        }

        .bow-tail-l, .bow-tail-r {
            position: absolute;
            top: 22px;
            width: 8px;
            height: 24px;
            background: linear-gradient(to bottom, #d62828, #9d0208);
            border-radius: 2px;
        }

        .bow-tail-l {
            left: 28px;
            transform: rotate(25deg);
        }

        .bow-tail-r {
            right: 28px;
            transform: rotate(-25deg);
        }

        .card-heading {
            font-family: 'Great Vibes', cursive;
            font-size: 46px;
            color: #b38b2d;
            margin: 12px 0 4px 0;
            font-weight: 400;
        }

        .card-subheading {
            font-size: 13px;
            color: #8c9196;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .card-price {
            font-size: 48px;
            font-weight: 800;
            color: #d62828;
            margin-bottom: 24px;
            letter-spacing: -1px;
        }

        .code-box {
            background-color: #f7f3eb;
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            padding: 14px 20px;
            margin: 0 auto 32px auto;
            max-width: 320px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .code-value {
            font-family: monospace;
            font-size: 20px;
            font-weight: 750;
            color: #2b2d42;
            letter-spacing: 1.5px;
        }

        .btn-copy-card {
            background-color: #e5ba73;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 8px rgba(229, 186, 115, 0.3);
        }

        .btn-copy-card:hover {
            background-color: #c5a059;
            transform: translateY(-1px);
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            text-align: left;
            margin-bottom: 36px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 24px 0;
        }

        .details-item {
            display: flex;
            flex-direction: column;
        }

        .details-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #8c9196;
            letter-spacing: 1px;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .details-text {
            font-size: 15px;
            color: #2b2d42;
            font-weight: 600;
        }

        .details-text.italic {
            font-style: italic;
            font-weight: 400;
            color: #4a4e69;
        }

        .actions-row {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        .btn-download-pdf {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #2b2d42;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.2s ease;
            flex: 1;
            max-width: 200px;
            box-shadow: 0 6px 12px rgba(43, 45, 66, 0.15);
        }

        .btn-download-pdf:hover {
            background-color: #1d1e2c;
            transform: translateY(-1px);
        }

        .btn-shop-now {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #d62828;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.2s ease;
            flex: 1;
            max-width: 200px;
            box-shadow: 0 6px 12px rgba(214, 40, 40, 0.2);
        }

        .btn-shop-now:hover {
            background-color: #b31e1e;
            transform: translateY(-1px);
        }

        .card-footer-note {
            font-size: 13px;
            color: #8c9196;
            margin-top: 36px;
            font-weight: 600;
        }

        /* Ambient background balloons */
        .balloon-ambient {
            opacity: 0.25;
            pointer-events: none;
        }

        /* Unboxing Experience CSS */
        #unboxing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at center, #2e1114 0%, #0f0506 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            transition: opacity 0.8s ease, visibility 0.8s ease;
            overflow: hidden;
        }

        #unboxing-overlay.fade-out {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        /* Bokeh light background effects */
        .bokeh-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 1;
        }

        .bokeh-dot {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.08);
            filter: blur(40px);
            animation: floatBokeh 15s infinite ease-in-out;
        }

        .dot-1 { width: 150px; height: 150px; top: 10%; left: 15%; animation-delay: 0s; }
        .dot-2 { width: 220px; height: 220px; bottom: 15%; right: 10%; animation-delay: -3s; }
        .dot-3 { width: 120px; height: 120px; top: 60%; left: 8%; animation-delay: -6s; }
        .dot-4 { width: 180px; height: 180px; top: 25%; right: 20%; animation-delay: -9s; }
        .dot-5 { width: 160px; height: 160px; bottom: 30%; left: 40%; animation-delay: -12s; }

        @keyframes floatBokeh {
            0%, 100% { transform: translateY(0) translateX(0) scale(1); }
            50% { transform: translateY(-40px) translateX(30px) scale(1.15); }
        }

        .unboxing-content {
            text-align: center;
            user-select: none;
            z-index: 10;
        }

        .gift-box-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            perspective: 1000px;
            position: relative;
        }

        /* Ambient Aura Glow behind Box */
        .gift-box-glow {
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(214, 40, 40, 0.22) 0%, rgba(214, 40, 40, 0) 70%);
            z-index: 1;
            pointer-events: none;
            animation: pulseGlow 3s infinite ease-in-out;
        }

        @keyframes pulseGlow {
            0%, 100% { transform: translate(-50%, -50%) scale(0.9); opacity: 0.8; }
            50% { transform: translate(-50%, -50%) scale(1.15); opacity: 1; }
        }

        /* 3D Cream-white Box with Red Ribbon */
        .gift-box {
            position: relative;
            width: 160px;
            height: 160px;
            background: linear-gradient(135deg, #ffffff 0%, #f4f6f8 100%);
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.8);
            transform-style: preserve-3d;
            z-index: 5;
            animation: idleFloat 3s infinite ease-in-out;
            transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1), opacity 0.6s ease;
        }

        .gift-box-lid {
            position: absolute;
            top: -24px;
            left: -10px;
            width: 180px;
            height: 32px;
            background: linear-gradient(135deg, #ffffff 0%, #eaeaea 100%);
            border-radius: 6px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2), inset 0 1px 1px rgba(255, 255, 255, 0.9);
            z-index: 10;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.8s ease;
        }

        .gift-box-ribbon-v {
            position: absolute;
            top: 0;
            left: 70px;
            width: 20px;
            height: 100%;
            background: linear-gradient(to right, #d62828, #9d0208);
            z-index: 5;
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.15);
        }

        .gift-box-ribbon-h {
            position: absolute;
            top: 70px;
            left: 0;
            width: 100%;
            height: 20px;
            background: linear-gradient(to bottom, #d62828, #9d0208);
            z-index: 5;
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.15);
        }

        /* Red Ribbon Bow */
        .gift-box-bow {
            position: absolute;
            top: -46px;
            left: 60px;
            width: 40px;
            height: 40px;
            z-index: 15;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.8s ease;
        }

        .gift-box-bow::before, .gift-box-bow::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            border: 6px solid #d62828;
            border-radius: 50% 50% 0 50%;
            transform: rotate(-45deg);
            box-shadow: inset 0 2px 2px rgba(255, 255, 255, 0.3);
        }

        .gift-box-bow::after {
            left: 16px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(45deg);
        }

        .tap-message {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            margin-top: 48px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
            animation: pulseText 1.8s infinite ease-in-out;
            z-index: 10;
        }

        /* Animation States when Opened */
        .gift-box-container.opened .gift-box-lid {
            transform: translateY(-130px) rotate(-25deg) scale(0.8);
            opacity: 0;
        }

        .gift-box-container.opened .gift-box-bow {
            transform: translateY(-150px) rotate(35deg) scale(0.7);
            opacity: 0;
        }

        .gift-box-container.opened .gift-box {
            transform: scale(0.8) translateY(40px);
            opacity: 0;
        }

        .gift-box-container.opened .tap-message {
            opacity: 0;
            transform: scale(0.9);
            transition: all 0.4s ease;
        }

        .gift-box-container.opened .gift-box-glow {
            transform: translate(-50%, -50%) scale(1.6);
            opacity: 0;
            transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes idleFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            20% { transform: translateY(-8px) rotate(-2deg); }
            40% { transform: translateY(-12px) rotate(1deg); }
            60% { transform: translateY(-6px) rotate(-1deg); }
            80% { transform: translateY(-10px) rotate(1deg); }
        }

        @keyframes pulseText {
            0%, 100% { opacity: 0.7; transform: scale(0.97); }
            50% { opacity: 1; transform: scale(1.03); }
        }

        /* Glossy 3D Balloon Elements */
        .balloon {
            position: fixed;
            bottom: -150px;
            border-radius: 50% 50% 50% 50% / 40% 40% 60% 60%;
            z-index: 999999;
            pointer-events: none;
            box-shadow: inset -12px -12px 0 rgba(0, 0, 0, 0.15), 0 12px 24px rgba(0, 0, 0, 0.25);
            animation: floatUp linear forwards;
            background: radial-gradient(circle at 35% 35%, var(--balloon-color-light) 0%, var(--balloon-color-base) 70%, var(--balloon-color-dark) 100%);
        }

        /* Balloon Highlight glare */
        .balloon-glare {
            position: absolute;
            top: 10%;
            left: 15%;
            width: 25%;
            height: 25%;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            transform: rotate(-30deg);
        }

        .balloon-knot {
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 8px solid;
            border-bottom-color: inherit;
        }

        .balloon-string {
            position: absolute;
            bottom: -50px;
            left: 50%;
            width: 1.5px;
            height: 45px;
            background: rgba(255, 255, 255, 0.35);
        }

        @keyframes floatUp {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-60vh) rotate(10deg); }
            100% { transform: translateY(-130vh) rotate(-10deg); }
        }
    </style>
</head>
<body>
    <!-- Unboxing Experience Overlay -->
    <div id="unboxing-overlay">
        <!-- Floating Bokeh Particles Backdrop -->
        <div class="bokeh-wrapper">
            <div class="bokeh-dot dot-1"></div>
            <div class="bokeh-dot dot-2"></div>
            <div class="bokeh-dot dot-3"></div>
            <div class="bokeh-dot dot-4"></div>
            <div class="bokeh-dot dot-5"></div>
        </div>

        <div class="unboxing-content">
            <div class="gift-box-container" onclick="openGift()">
                <!-- Halo aura glow behind box -->
                <div class="gift-box-glow"></div>

                <div class="gift-box">
                    <div class="gift-box-bow"></div>
                    <div class="gift-box-lid">
                        <div class="gift-box-ribbon-v" style="left: 80px; width: 20px;"></div>
                    </div>
                    <div class="gift-box-ribbon-v"></div>
                    <div class="gift-box-ribbon-h"></div>
                </div>
                <div class="tap-message">🎁 Tap to Open Your Gift</div>
            </div>
        </div>
    </div>

    <div class="page-container">
        <!-- Premium Gift Card Card Container -->
        <div class="premium-card">
            <!-- Red Bow at the Top of the Card -->
            <div class="premium-card-bow">
                <div class="bow-loop-l"></div>
                <div class="bow-loop-r"></div>
                <div class="bow-tail-l"></div>
                <div class="bow-tail-r"></div>
                <div class="bow-knot"></div>
            </div>

            <!-- Card Header -->
            <h1 class="card-heading">A Special Gift For You!</h1>
            <p class="card-subheading">You have received a Gift Card</p>

            <!-- Card Amount -->
            <div class="card-price">
                ${{ number_format((float) $voucher->remaining_balance, 2) }}
            </div>

            <!-- Voucher Code Box -->
            <div class="code-box">
                <span class="code-value" id="voucherCode">{{ $voucher->code }}</span>
                <button class="btn-copy-card" id="btnCopy" onclick="copyVoucherCode()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; display: inline-block; vertical-align: middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    Copy
                </button>
            </div>

            <!-- Card Details Grid -->
            <div class="details-grid">
                <div class="details-item">
                    <span class="details-label">From</span>
                    <span class="details-text">{{ $voucher->sender_name ?: 'A Friend' }}</span>
                </div>
                <div class="details-item">
                    <span class="details-label">To</span>
                    <span class="details-text">{{ $voucher->recipient_name }}</span>
                </div>
                <div class="details-item">
                    <span class="details-label">Valid Till</span>
                    <span class="details-text">{{ $voucher->expires_at ? $voucher->expires_at->format('d M Y') : 'No Expiry' }}</span>
                </div>
                <div class="details-item">
                    <span class="details-label">Message</span>
                    <span class="details-text italic">"{{ $voucher->personal_message ?: 'Enjoy your special gift!' }}"</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="actions-row">
                <a href="{{ route('shopify.storefront.download-pdf', ['secureToken' => $secureToken]) }}" class="btn-download-pdf">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; display: inline-block; vertical-align: middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Download PDF
                </a>
                <a href="https://{{ $shopName }}" target="_blank" class="btn-shop-now">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; display: inline-block; vertical-align: middle;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    Shop Now
                </a>
            </div>

            <!-- Card Footer Note -->
            <div class="card-footer-note">
                Thank you for choosing {{ $shopName }}! ❤️
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script>
        function copyVoucherCode() {
            const codeText = document.getElementById('voucherCode').innerText;
            const btnCopy = document.getElementById('btnCopy');
            
            navigator.clipboard.writeText(codeText).then(() => {
                btnCopy.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; display: inline-block; vertical-align: middle;"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!';
                btnCopy.style.backgroundColor = '#b38b2d';
                
                setTimeout(() => {
                    btnCopy.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; display: inline-block; vertical-align: middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copy';
                    btnCopy.style.backgroundColor = '#e5ba73';
                }, 2000);
            }).catch(err => {
                console.error('Could not copy code: ', err);
            });
        }

        let giftOpened = false;

        function createBalloon() {
            const baseColors = ['#ff3366', '#3388ff', '#10d080', '#ffa800', '#f040aa', '#9050ff'];
            const lightColors = ['#ff88aa', '#88bbff', '#60f0b0', '#ffd060', '#ff8ad0', '#c89aff'];
            const darkColors = ['#990022', '#0044aa', '#007030', '#aa6000', '#aa0060', '#5000aa'];
            
            const balloon = document.createElement('div');
            balloon.className = 'balloon';
            balloon.style.left = Math.random() * 100 + 'vw';
            
            const idx = Math.floor(Math.random() * baseColors.length);
            const baseC = baseColors[idx];
            const lightC = lightColors[idx];
            const darkC = darkColors[idx];
            
            balloon.style.setProperty('--balloon-color-light', lightC);
            balloon.style.setProperty('--balloon-color-base', baseC);
            balloon.style.setProperty('--balloon-color-dark', darkC);
            balloon.style.color = baseC; // Knot matches base color
            
            balloon.style.animationDuration = (Math.random() * 3 + 4) + 's';
            
            const size = Math.random() * 25 + 35;
            balloon.style.width = size + 'px';
            balloon.style.height = (size * 1.25) + 'px';
            
            // Add Glare Highlight
            const glare = document.createElement('div');
            glare.className = 'balloon-glare';
            balloon.appendChild(glare);
            
            // Add Knot
            const knot = document.createElement('div');
            knot.className = 'balloon-knot';
            balloon.appendChild(knot);
            
            // Add String
            const string = document.createElement('div');
            string.className = 'balloon-string';
            balloon.appendChild(string);
            
            document.body.appendChild(balloon);
            setTimeout(() => balloon.remove(), 7000);
        }

        function createAmbientBalloon() {
            const baseColors = ['rgba(255, 51, 102, 0.4)', 'rgba(51, 136, 255, 0.4)', 'rgba(16, 208, 128, 0.4)', 'rgba(255, 168, 0, 0.4)', 'rgba(144, 80, 255, 0.4)'];
            const lightColors = ['rgba(255, 136, 170, 0.4)', 'rgba(136, 187, 255, 0.4)', 'rgba(96, 240, 176, 0.4)', 'rgba(255, 208, 96, 0.4)', 'rgba(200, 154, 255, 0.4)'];
            const darkColors = ['rgba(153, 0, 34, 0.4)', 'rgba(0, 68, 170, 0.4)', 'rgba(0, 112, 48, 0.4)', 'rgba(170, 96, 0, 0.4)', 'rgba(80, 0, 170, 0.4)'];
            
            const balloon = document.createElement('div');
            balloon.className = 'balloon balloon-ambient';
            balloon.style.left = (Math.random() * 90 + 5) + 'vw';
            
            const idx = Math.floor(Math.random() * baseColors.length);
            const baseC = baseColors[idx];
            const lightC = lightColors[idx];
            const darkC = darkColors[idx];
            
            balloon.style.setProperty('--balloon-color-light', lightC);
            balloon.style.setProperty('--balloon-color-base', baseC);
            balloon.style.setProperty('--balloon-color-dark', darkC);
            balloon.style.color = baseC;
            
            balloon.style.animationDuration = (Math.random() * 8 + 12) + 's';
            balloon.style.zIndex = '1';
            
            const size = Math.random() * 30 + 40;
            balloon.style.width = size + 'px';
            balloon.style.height = (size * 1.25) + 'px';
            balloon.style.filter = 'blur(1px)';
            
            // Add Glare Highlight
            const glare = document.createElement('div');
            glare.className = 'balloon-glare';
            glare.style.opacity = '0.5';
            balloon.appendChild(glare);
            
            // Add Knot
            const knot = document.createElement('div');
            knot.className = 'balloon-knot';
            balloon.appendChild(knot);
            
            // Add String
            const string = document.createElement('div');
            string.className = 'balloon-string';
            balloon.appendChild(string);
            
            document.body.appendChild(balloon);
            setTimeout(() => balloon.remove(), 21000);
        }

        function startAmbientBalloons() {
            for (let i = 0; i < 4; i++) {
                setTimeout(createAmbientBalloon, i * 800);
            }
            setInterval(() => {
                if (document.querySelectorAll('.balloon-ambient').length < 12) {
                    createAmbientBalloon();
                }
            }, 2500);
        }

        function openGift() {
            if (giftOpened) return;
            giftOpened = true;

            const container = document.querySelector('.gift-box-container');
            container.classList.add('opened');

            // 1. Trigger 5 sequential major confetti explosions (BOM)
            for (let b = 0; b < 5; b++) {
                setTimeout(() => {
                    const origins = [
                        { x: 0.5, y: 0.65 }, // Center burst
                        { x: 0.25, y: 0.7 }, // Left burst
                        { x: 0.75, y: 0.7 }, // Right burst
                        { x: 0.35, y: 0.55 }, // Higher Left
                        { x: 0.65, y: 0.55 }  // Higher Right
                    ];
                    const colorsList = [
                        ['#ff3366', '#ffa800', '#ffffff', '#ff00aa'],
                        ['#3388ff', '#10d080', '#9050ff', '#ffffff'],
                        ['#ff3366', '#ffa800', '#10d080', '#ffffff'],
                        ['#9050ff', '#ffa800', '#3388ff', '#ffffff'],
                        ['#ff3366', '#10d080', '#9050ff', '#ffffff']
                    ];
                    
                    // Create massive celebratory particle burst
                    confetti({
                        particleCount: 180,
                        spread: b === 0 ? 120 : 80,
                        startVelocity: b === 0 ? 55 : 45,
                        origin: origins[b],
                        colors: colorsList[b],
                        scalar: 1.2
                    });
                }, b * 220);
            }

            // 2. Spawn 55 Floating 3D Balloons in fast waves
            for (let i = 0; i < 55; i++) {
                setTimeout(createBalloon, i * 45);
            }

            // 3. Ambient Fireworks Show overlay
            let duration = 2.8 * 1000;
            let animationEnd = Date.now() + duration;
            let defaults = { startVelocity: 35, spread: 360, ticks: 70, zIndex: 10000 };

            let interval = setInterval(function() {
                let timeLeft = animationEnd - Date.now();

                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }

                let particleCount = 60 * (timeLeft / duration);
                confetti({ ...defaults, particleCount, origin: { x: Math.random() * 0.4 + 0.1, y: Math.random() - 0.25 } });
                confetti({ ...defaults, particleCount, origin: { x: Math.random() * 0.4 + 0.5, y: Math.random() - 0.25 } });
            }, 200);

            // 4. Transition to Details Page
            setTimeout(() => {
                const overlay = document.getElementById('unboxing-overlay');
                const pageContainer = document.querySelector('.page-container');
                
                overlay.classList.add('fade-out');
                
                pageContainer.style.display = 'block';
                setTimeout(() => {
                    pageContainer.style.opacity = '1';
                    
                    // Grand 360-degree confetti hit from all sides!
                    // 1. Center Burst
                    confetti({
                        particleCount: 160,
                        spread: 120,
                        origin: { x: 0.5, y: 0.45 }
                    });
                    // 2. Top-Left Corner (shooting down-right)
                    confetti({
                        particleCount: 100,
                        angle: 60,
                        spread: 80,
                        origin: { x: 0, y: 0 }
                    });
                    // 3. Top-Right Corner (shooting down-left)
                    confetti({
                        particleCount: 100,
                        angle: 120,
                        spread: 80,
                        origin: { x: 1, y: 0 }
                    });
                    // 4. Bottom-Left Corner (shooting up-right)
                    confetti({
                        particleCount: 110,
                        angle: 45,
                        spread: 80,
                        origin: { x: 0, y: 0.85 }
                    });
                    // 5. Bottom-Right Corner (shooting up-left)
                    confetti({
                        particleCount: 110,
                        angle: 135,
                        spread: 80,
                        origin: { x: 1, y: 0.85 }
                    });

                    startAmbientBalloons();
                }, 50);
                
                setTimeout(() => {
                    overlay.remove();
                }, 1000);
            }, 2.8 * 1000);
        }
    </script>
</body>
</html>
