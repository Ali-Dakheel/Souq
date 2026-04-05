<?php

declare(strict_types=1);

use App\Modules\Returns\Controllers\ReturnRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api/v1')->group(function (): void {
    Route::get('orders/{orderNumber}/returns', [ReturnRequestController::class, 'index']);
    Route::post('orders/{orderNumber}/returns', [ReturnRequestController::class, 'store']);
});
