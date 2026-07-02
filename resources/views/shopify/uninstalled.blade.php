<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App Cleaned and Uninstalled</title>
    <link rel="stylesheet" href="https://unpkg.com/@shopify/polaris@12.0.0/build/esm/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            background: #f6f6f7;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #202223;
        }
        .container {
            max-width: 480px;
            width: 100%;
            padding: 24px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="Polaris-Card">
            <div class="Polaris-Card__Header" style="padding: 24px; text-align: center;">
                <h1 class="Polaris-Text--headingLg">App Uninstalled Successfully</h1>
            </div>
            <div class="Polaris-Card__Section" style="border-top: 1px solid var(--p-color-border-subdued); padding: 24px; text-align: center;">
                <p style="color: var(--p-color-text-secondary); margin-bottom: 24px;">
                    All storefront resources (the "Gift Card" page and navigation link) have been completely removed from <strong>{{ $shopDomain }}</strong>, and the app has been deactivated.
                </p>
                <div style="background-color: var(--p-color-bg-surface-success, #e7f5ef); border: 1px solid var(--p-color-border-success, #a3e0c4); border-radius: 6px; padding: 12px; color: var(--p-color-text-success, #1e5e41); font-size: 14px; margin-bottom: 24px;">
                    GDPR data redaction and database purge have been completed.
                </div>
                <p style="font-size: 13px; color: var(--p-color-text-subdued);">
                    You can safely close this browser window.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
