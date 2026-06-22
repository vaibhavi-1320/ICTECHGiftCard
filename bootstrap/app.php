<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AllowShopifyFrame;
use App\Http\Middleware\EnsureShopifyQuery;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', AllowShopifyFrame::class);
        $middleware->appendToGroup('web', EnsureShopifyQuery::class);
        
        $middleware->alias([
            'shopify.session' => \App\Http\Middleware\VerifyShopifySession::class,
            'shopify.webhook' => \App\Http\Middleware\VerifyShopifyWebhook::class,
            'shopify.proxy' => \App\Http\Middleware\VerifyShopifyProxy::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'shopify/*',
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
