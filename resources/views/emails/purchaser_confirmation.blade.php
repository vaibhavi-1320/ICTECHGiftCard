<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Card Delivered</title>
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
                            <h1>Gift Card Sent Successfully!</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <div class="greeting">Thank you for your purchase!</div>
                            <div class="intro-text">
                                We are writing to confirm that the <strong>{{ $amount }}</strong> Gift Card you purchased has been successfully generated and delivered to <strong>{{ $recipientName }}</strong> ({{ $recipientEmail }}).
                            </div>

                            <!-- Voucher Info Table -->
                            <table class="info-table" width="100%">
                                <tr class="info-row">
                                    <td class="info-label">Recipient</td>
                                    <td class="info-value">{{ $recipientName }} ({{ $recipientEmail }})</td>
                                </tr>
                                <tr class="info-row">
                                    <td class="info-label">Gift Card Amount</td>
                                    <td class="info-value">{{ $amount }}</td>
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
