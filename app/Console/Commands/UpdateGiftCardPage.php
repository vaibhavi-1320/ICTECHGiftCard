<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use Illuminate\Console\Command;

class UpdateGiftCardPage extends Command
{
    protected $signature   = 'gift-cards:update-page {--shop= : Shopify domain}';
    protected $description = 'Push the latest gift card page body_html to Shopify.';

    public function __construct(private readonly ShopifyService $shopifyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $domain = $this->option('shop') ?: 'gift-card-store-4ho1kqgj.myshopify.com';
        $shop   = Shop::where('shopify_domain', $domain)->first();

        if (!$shop) {
            $this->error("Shop [{$domain}] not found.");
            return self::FAILURE;
        }

        $this->info("Updating gift card page for [{$domain}]...");
        $this->shopifyService->createStorefrontResources($shop);
        $this->info('Done.');
        return self::SUCCESS;
    }
}
