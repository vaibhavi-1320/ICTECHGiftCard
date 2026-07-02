<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Card Scheduled</title>
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
            background-color: #008060;
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
                            <h1>Gift Card Scheduled Successfully!</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <div class="greeting">Thank you for your purchase!</div>
                            <div class="intro-text">
                                We are writing to confirm that the <strong>{{ $amount }}</strong> Gift Card you purchased has been successfully scheduled to be delivered to <strong>{{ $recipientName }}</strong> ({{ $recipientEmail }}) on <strong>{{ $scheduledSendDate }}</strong>.
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

                            <!-- Voucher Info Table -->
                            <table class="info-table" width="100%">
                                <tr class="info-row">
                                    <td class="info-label">Recipient</td>
                                    <td class="info-value">{{ $recipientName }} ({{ $recipientEmail }})</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Voucher Code</td>
                                    <td class="info-value" style="font-family: monospace; letter-spacing: 1px; color: #888888;">XXX-XXX-XXX</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Gift Card Amount</td>
                                    <td class="info-value">{{ $amount }}</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Scheduled Date</td>
                                    <td class="info-value">{{ $scheduledSendDate }}</td>
                                </tr>
                                @if(!empty($personalMessage))
                                    <tr class="info-row">
                                        <td class="info-label">Your Message</td>
                                        <td class="info-value" style="font-style: italic;">"{{ $personalMessage }}"</td>
                                    </tr>
                                @endif
                            </table>

                            <div style="font-size: 14px; line-height: 1.6; color: #6d7175; text-align: center;">
                                If you need to make any updates or have questions about your order, please do not hesitate to contact our support team.
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            {{ $shopName }}<br>
                            Thank you for shopping with us!
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
