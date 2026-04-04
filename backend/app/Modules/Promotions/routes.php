<?php

declare(strict_types=1);

use App\Modules\Promotions\Controllers\PromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('promotions/applicable', [PromotionController::class, 'applicable']);
});
