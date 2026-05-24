<?php

use App\Http\Controllers\PromoCodeController;
use App\Http\Middleware\AdminTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/promo-codes')->middleware(AdminTokenAuth::class)->group(function () {
    Route::get('/', [PromoCodeController::class, 'adminList'])->name('admin.api.promo-codes');
    Route::post('/', [PromoCodeController::class, 'adminCreate'])->name('admin.api.promo-codes.create');
    Route::post('/delete', [PromoCodeController::class, 'adminDelete'])->name('admin.api.promo-codes.delete');
});
