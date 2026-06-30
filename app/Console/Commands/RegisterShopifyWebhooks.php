<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\Shopify\ShopifyService;
use Illuminate\Console\Command;

class RegisterShopifyWebhooks extends Command
{
    protected $signature = 'shopify:register-webhooks {--force : Re-register even if already registered}';
    protected $description = 'Register required Shopify webhooks for all installed shops';

    public function handle(ShopifyService $service): int
    {
        $shops = Shop::whereNotNull('access_token')->get();

        if ($shops->isEmpty()) {
            $this->error('No installed shops found.');
            return self::FAILURE;
        }

        $webhooks = [
            [
                'topic'   => 'orders/paid',
                'address' => url('/webhooks/orders-paid'),
                'format'  => 'json',
            ],
            [
                'topic'   => 'orders/create',
                'address' => url('/webhooks/orders-created'),
                'format'  => 'json',
            ],
        ];

        foreach ($shops as $shop) {
            $this->line("Processing shop: <info>{$shop->shopify_domain}</info>");

            // Fetch existing webhooks
            $existing = [];
            $res = $service->api($shop, 'GET', 'webhooks.json');
            if ($res->successful()) {
                foreach ($res->json('webhooks') ?? [] as $hook) {
                    $existing[$hook['topic']] = $hook;
                }
            }

            foreach ($webhooks as $webhook) {
                $topic = $webhook['topic'];

                if (isset($existing[$topic])) {
                    $existingAddress = $existing[$topic]['address'] ?? '';
                    if ($existingAddress !== $webhook['address']) {
                        // Address changed (e.g. domain update). Re-register.
                        $service->api($shop, 'DELETE', "webhooks/{$existing[$topic]['id']}.json");
                        $this->line("  Address mismatch (existing: {$existingAddress}, new: {$webhook['address']}) → deleted old <comment>{$topic}</comment> webhook.");
                    } else if ($this->option('force')) {
                        // Delete and re-create
                        $service->api($shop, 'DELETE', "webhooks/{$existing[$topic]['id']}.json");
                        $this->line("  Deleted old <comment>{$topic}</comment> webhook.");
                    } else {
                        $this->line("  <comment>{$topic}</comment> already registered with correct address → skip");
                        continue;
                    }
                }

                $createRes = $service->api($shop, 'POST', 'webhooks.json', ['webhook' => $webhook]);

                if ($createRes->successful()) {
                    $hookId = $createRes->json('webhook.id');
                    $this->line("  ✓ Registered <info>{$topic}</info> (id: {$hookId}) → {$webhook['address']}");
                } else {
                    $this->error("  ✗ Failed to register {$topic}: " . $createRes->body());
                }
            }
        }

        $this->newLine();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
