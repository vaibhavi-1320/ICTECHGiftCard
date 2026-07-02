<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A Gift For You</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f8;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #202223;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f6f8;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .header {
            background-color: #008060; /* Shopify primary color */
            padding: 32px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 12px;
        }
        .content {
            padding: 40px 32px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .intro-text {
            font-size: 15px;
            line-height: 1.6;
            color: #6d7175;
            margin-bottom: 32px;
        }
        .card-preview-container {
            text-align: center;
            margin-bottom: 32px;
        }
        .card-preview {
            max-width: 100%;
            width: 320px;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            display: inline-block;
            transition: all 0.3s ease;
        }
        .card-preview:hover {
            transform: scale(1.04) translateY(-3px);
            box-shadow: 0 16px 36px rgba(0, 128, 96, 0.22);
            filter: brightness(1.06) contrast(1.02);
        }
        .fallback-card {
            width: 320px;
            height: 192px;
            background: linear-gradient(135deg, #008060 0%, #004b36 100%);
            border-radius: 12px;
            display: inline-flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #ffffff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        .fallback-amount {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .fallback-tag {
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.8;
        }
        .message-box {
            background-color: #f9fafb;
            border-left: 4px solid #008060;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 32px;
            font-style: italic;
            color: #454f5b;
            font-size: 15px;
            line-height: 1.6;
        }
        .cta-container {
            text-align: center;
            margin-bottom: 32px;
        }
        .cta-button {
            display: inline-block;
            background-color: #008060;
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(0, 128, 96, 0.2);
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            background-color: #004b36 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 75, 54, 0.3) !important;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
            border-top: 1px solid #f1f2f4;
            border-bottom: 1px solid #f1f2f4;
            padding: 16px 0;
        }
        .info-row td {
            padding: 12px 0;
            font-size: 14px;
        }
        .info-label {
            color: #6d7175;
            font-weight: 500;
        }
        .info-value {
            text-align: right;
            font-weight: 600;
            color: #202223;
        }
        .footer {
            text-align: center;
            padding: 32px;
            font-size: 13px;
            color: #8c9196;
            background-color: #fafbfb;
            border-top: 1px solid #f1f2f4;
        }
        .footer a {
            color: #008060;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <table class="wrapper" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table class="container" width="100%" cellpadding="0" cellspacing="0">
                    <!-- Header -->
                    <tr>
                        <td class="header">
                            @if($shopLogoUrl)
                                <img src="{{ $shopLogoUrl }}" alt="{{ $shopName }}" class="logo">
                            @endif
                            <h1>A Gift for You!</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td class="content">
                            @php
                                $maskedCode = substr($code, 0, min(8, strlen($code))) . str_repeat('X', max(0, strlen($code) - 8));
                            @endphp
                            <div class="greeting">Hi {{ $recipientName }},</div>
                            <div class="intro-text">
                                🎉 You've received a Gift Card!<br><br>
                                <strong>{{ $senderName }}</strong> has sent you a <strong>{{ $amount }}</strong> Gift Card from <strong>{{ $shopName }}</strong>.
                            </div>

                            <!-- Personal Message -->
                            @if(!empty($personalMessage))
                                <div class="intro-text" style="margin-bottom: 8px; font-weight: 600;">Personal Message:</div>
                                <div class="message-box">
                                    "{{ $personalMessage }}"
                                </div>
                            @endif

                            <div class="intro-text">
                                Your unique Gift Card voucher is attached as a PDF. You can also click the "Open Gift Card" button below to view your digital Gift Card online.
                            </div>

                            <!-- Card Preview Image -->
                            <div class="card-preview-container">
                                @if($templateMediaUrl)
                                    <img src="{{ $templateMediaUrl }}" alt="Gift Card" class="card-preview" width="320" style="display: inline-block; outline: none; border: none; text-decoration: none; max-width: 100%; height: auto; border-radius: 12px;">
                                @else
                                    <div class="fallback-card">
                                        <div class="fallback-amount">{{ $amount }}</div>
                                        <div class="fallback-tag">Gift Card</div>
                                    </div>
                                @endif
                            </div>

                            <!-- CTA Button -->
                            <div class="cta-container">
                                <a href="{{ $openUrl }}" target="_blank" class="cta-button">Open Gift Card</a>
                            </div>

                            <!-- Gift Card Details Header -->
                            <div style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #202223;">
                                Gift Card Details
                            </div>

                            <!-- Voucher Info Table -->
                            <table class="info-table" width="100%">
                                <tr class="info-row">
                                    <td class="info-label">Voucher Code</td>
                                    <td class="info-value" style="font-family: monospace; letter-spacing: 1px; font-size: 16px; color: #008060;">{{ $maskedCode }}</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Amount</td>
                                    <td class="info-value">{{ $amount }}</td>
                                </tr>
                                @if($expiryDate)
                                    <tr class="info-row">
                                        <td class="info-label">Valid Until</td>
                                        <td class="info-value">{{ $expiryDate }}</td>
                                    </tr>
                                @endif
                            </table>

                            <div style="font-size: 14px; line-height: 1.6; color: #6d7175; margin-bottom: 24px;">
                                <strong>How to Redeem:</strong> Simply enter the code <strong>{{ $maskedCode }}</strong> in the checkout discount box when placing your next order at {{ $shopName }}.
                            </div>

                            <div class="intro-text" style="margin-top: 32px; margin-bottom: 0;">
                                We hope you enjoy your gift and have a wonderful shopping experience!<br><br>
                                Happy Shopping!<br>
                                <strong>The {{ $shopName }} Team</strong>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            Sent by {{ $shopName }}<br>
                            If you have any questions, feel free to contact us.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
