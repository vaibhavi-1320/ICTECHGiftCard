<?php

use App\Http\Controllers\AdminShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('shopify.app', request()->query());
});

Route::get('/admin', AdminShellController::class)->name('admin.shell');
Route::get('/shopify/app', \App\Http\Controllers\Shopify\AppController::class)->name('shopify.app');

// Public storefront routes for Gift Card recipients
Route::get('/gift-card/open/{secureToken}', [\App\Http\Controllers\Shopify\StorefrontController::class, 'openGiftCard'])->name('shopify.storefront.open');
Route::get('/gift-card/download-pdf/{secureToken}', [\App\Http\Controllers\Shopify\StorefrontController::class, 'downloadPdf'])->name('shopify.storefront.download-pdf');

Route::middleware('shopify.proxy')->group(function (): void {
    Route::post('/storefront/gift-cards/validate', [\App\Http\Controllers\Shopify\StorefrontController::class, 'validateGiftCard']);
    Route::post('/gift-cards/storefront/gift-cards/validate', [\App\Http\Controllers\Shopify\StorefrontController::class, 'validateGiftCard']);
    Route::post('/storefront/gift-cards/remove', [\App\Http\Controllers\Shopify\StorefrontController::class, 'removeGiftCard']);
    Route::post('/gift-cards/storefront/gift-cards/remove', [\App\Http\Controllers\Shopify\StorefrontController::class, 'removeGiftCard']);
});

require __DIR__.'/shopify.php';
