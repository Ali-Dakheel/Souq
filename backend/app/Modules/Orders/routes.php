<?php

use App\Modules\Orders\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('api')->group(function () {

    // Guest order lookup (no auth required — verified by email)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('orders/{orderNumber}/guest', [OrderController::class, 'showGuest']);
    });

    // Checkout and cancel — strict rate limit
    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel']);
    });

    // Order list and detail — standard rate limit
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{orderNumber}', [OrderController::class, 'show']);
        Route::get('orders/{orderNumber}/invoice', [OrderController::class, 'invoice']);
        Route::get('orders/{orderNumber}/shipments', [OrderController::class, 'shipments']);
    });
});
