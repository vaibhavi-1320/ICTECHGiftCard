<?php

use App\Http\Controllers\Shopify\AuthController;
use App\Http\Controllers\Shopify\AppController;
use App\Http\Controllers\Shopify\GiftCardController;
use App\Http\Controllers\Shopify\GiftCardTemplateController;
use App\Http\Controllers\Shopify\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/shopify/install', [AuthController::class, 'install'])->name('shopify.install');
Route::get('/shopify/callback', [AuthController::class, 'callback'])->name('shopify.callback');
Route::get('/shopify/app', AppController::class)->middleware('shopify.session')->name('shopify.app');
Route::get('/shopify/dashboard', AppController::class)->middleware('shopify.session')->name('shopify.dashboard');

Route::prefix('/shopify')->name('shopify.')->middleware('shopify.session')->group(function (): void {
    Route::get('/gift-cards', [GiftCardController::class, 'index'])->name('gift-cards.index');
    Route::get('/gift-cards/create', [GiftCardController::class, 'create'])->name('gift-cards.create');
    Route::post('/gift-cards', [GiftCardController::class, 'store'])->name('gift-cards.store');
    Route::get('/gift-cards/{giftCard}/edit', [GiftCardController::class, 'edit'])->name('gift-cards.edit');
    Route::put('/gift-cards/{giftCard}', [GiftCardController::class, 'update'])->name('gift-cards.update');
    Route::delete('/gift-cards/{giftCard}', [GiftCardController::class, 'destroy'])->name('gift-cards.destroy');

    Route::get('/templates', [GiftCardTemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [GiftCardTemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [GiftCardTemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{templateId}/edit', [GiftCardTemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{templateId}', [GiftCardTemplateController::class, 'update'])->name('templates.update');
    Route::get('/templates/{templateId}/preview-pdf', [GiftCardTemplateController::class, 'previewPdf'])->name('templates.preview-pdf');

    Route::get('/settings', [\App\Http\Controllers\Shopify\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [\App\Http\Controllers\Shopify\SettingsController::class, 'update'])->name('settings.update');

    Route::get('/dashboard/purchased-export', [\App\Http\Controllers\Shopify\AppController::class, 'purchasedExport'])->name('dashboard.purchased-export');
    Route::get('/dashboard/used-export', [\App\Http\Controllers\Shopify\AppController::class, 'usedExport'])->name('dashboard.used-export');
});


Route::middleware('shopify.proxy')->group(function (): void {
    Route::get('/storefront/gift-cards', [\App\Http\Controllers\Shopify\StorefrontController::class, 'index'])->name('shopify.storefront.gift-cards');
    Route::get('/gift-cards/storefront/gift-cards', [\App\Http\Controllers\Shopify\StorefrontController::class, 'index']);
});

Route::post('/webhooks/orders-created', [WebhookController::class, 'ordersCreated'])->middleware('shopify.webhook')->name('shopify.webhooks.orders-created');
Route::post('/webhooks/orders-paid', [WebhookController::class, 'ordersPaid'])->middleware('shopify.webhook')->name('shopify.webhooks.orders-paid');
