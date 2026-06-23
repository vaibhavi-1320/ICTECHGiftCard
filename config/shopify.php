<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'scopes' => array_filter(array_map('trim', explode(',', (string) env('SHOPIFY_SCOPES', 'read_products,write_products,read_orders,write_orders,read_customers,write_discounts')))),
    'app_url' => env('APP_URL'),
    'host_name' => env('SHOPIFY_HOST_NAME'),
    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),
    'embedded' => (bool) env('SHOPIFY_EMBEDDED_APP', true),
    'app_handle' => env('SHOPIFY_APP_HANDLE', 'ictechgiftcard'),
];
