<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A Scheduled Gift For You</title>
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
                            <h1>A Gift is Coming Your Way!</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <div class="greeting">Hi {{ $recipientName }},</div>
                            <div class="intro-text">
                                🎉 A digital Gift Card has been scheduled for you!<br><br>
                                <strong>{{ $senderName }}</strong> has purchased a <strong>{{ $amount }}</strong> Gift Card from <strong>{{ $shopName }}</strong>, set to be delivered to you on <strong>{{ $scheduledSendDate }}</strong>.
                            </div>

                            <!-- Personal Message -->
                            @if(!empty($personalMessage))
                                <div class="intro-text" style="margin-bottom: 8px; font-weight: 600;">Personal Message:</div>
                                <div class="message-box">
                                    "{{ $personalMessage }}"
                                </div>
                            @endif

                            <div class="intro-text">
                                Here is a preview of your upcoming gift card. Your unique voucher code and unboxing experience link will be sent to you automatically on <strong>{{ $scheduledSendDate }}</strong>.
                            </div>

                            <!-- Card Preview Image -->
                            <div class="card-preview-container">
                                @if($templateMediaUrl)
                                    <img src="{{ $templateMediaUrl }}" alt="Gift Card Preview" class="card-preview" width="320" style="display: inline-block; outline: none; border: none; text-decoration: none; max-width: 100%; height: auto; border-radius: 12px;">
                                @else
                                    <div class="fallback-card">
                                        <div class="fallback-amount">{{ $amount }}</div>
                                        <div class="fallback-tag">Gift Card</div>
                                    </div>
                                @endif
                            </div>

                            <!-- Gift Card Details Header -->
                            <div style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #202223;">
                                Gift Card Details
                            </div>

                            <!-- Voucher Info Table -->
                            <table class="info-table" width="100%">
                                <tr class="info-row">
                                    <td class="info-label">Voucher Code</td>
                                    <td class="info-value" style="font-family: monospace; letter-spacing: 1px; font-size: 16px; color: #888888;">XXX-XXX-XXX</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Amount</td>
                                    <td class="info-value">{{ $amount }}</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Scheduled Date</td>
                                    <td class="info-value">{{ $scheduledSendDate }}</td>
                                </tr>
                                @if($expiryDate)
                                    <tr class="info-row">
                                        <td class="info-label">Valid Until</td>
                                        <td class="info-value">{{ $expiryDate }}</td>
                                    </tr>
                                @endif
                            </table>

                            <div style="font-size: 14px; line-height: 1.6; color: #6d7175; margin-bottom: 24px;">
                                <strong>How to Redeem:</strong> Your unique code (hidden as <strong>XXX-XXX-XXX</strong> for now) will be unlocked and sent to you on <strong>{{ $scheduledSendDate }}</strong>. You can then redeem it on the checkout page of {{ $shopName }}.
                            </div>

                            <div class="intro-text" style="margin-top: 32px; margin-bottom: 0;">
                                We hope you look forward to receiving your gift card!<br><br>
                                Warm regards,<br>
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
