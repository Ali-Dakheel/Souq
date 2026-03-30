<?php

use App\Modules\Cart\Controllers\CartController;
use App\Modules\Cart\Controllers\CouponController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('api')->group(function () {

    // Cart — guest (X-Cart-Session header) or authenticated (Sanctum)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('cart', [CartController::class, 'show']);
    });

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('cart/add-item', [CartController::class, 'addItem']);
        Route::put('cart/items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('cart/items/{cartItem}', [CartController::class, 'removeItem']);
        Route::post('cart/remove-coupon', [CartController::class, 'removeCoupon']);
        Route::post('cart/clear', [CartController::class, 'clear']);
        Route::get('coupons/active', [CouponController::class, 'active']);
        Route::post('coupons/validate', [CouponController::class, 'validate']);
    });

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('cart/apply-coupon', [CartController::class, 'applyCoupon']);
    });

    // Merge requires authentication
    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('cart/merge', [CartController::class, 'merge']);
    });
});
