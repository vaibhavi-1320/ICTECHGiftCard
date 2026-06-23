<?php

use App\Http\Controllers\Api\GiftCardDashboardController;
use App\Http\Controllers\Api\GiftCardExportController;
use App\Http\Controllers\Api\GiftCardTemplatePreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('gift-cards')->group(function (): void {
    Route::get('dashboard', GiftCardDashboardController::class);
    Route::get('exports/purchased', [GiftCardExportController::class, 'purchased']);
    Route::get('exports/used', [GiftCardExportController::class, 'used']);
    Route::get('templates/preview', GiftCardTemplatePreviewController::class);
});
