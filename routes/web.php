<?php

use App\Http\Controllers\AdminShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('shopify.app', request()->query());
});

Route::get('/admin', AdminShellController::class)->name('admin.shell');
Route::get('/shopify/app', \App\Http\Controllers\Shopify\AppController::class)->name('shopify.app');

require __DIR__.'/shopify.php';
