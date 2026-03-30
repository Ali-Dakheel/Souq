<?php

use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('api')->group(function () {

    // Authenticated payment endpoints
    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('payments/charge', [PaymentController::class, 'charge']);
        Route::post('payments/{transaction}/refund', [PaymentController::class, 'requestRefund']);
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('payments/order/{orderId}', [PaymentController::class, 'orderPayment']);
        Route::get('payments/result', [PaymentController::class, 'result']);
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('webhooks/tap', WebhookController::class);
    });
});
