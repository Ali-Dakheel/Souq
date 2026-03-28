<?php

use App\Modules\Customers\Controllers\AddressController;
use App\Modules\Customers\Controllers\AuthController;
use App\Modules\Customers\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('api')->group(function () {

    // Auth — rate-limited to 60 requests per minute
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Authenticated auth endpoints
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    // Profile & addresses — rate-limited to 30 requests per minute, auth required
    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::get('customers/profile', [ProfileController::class, 'show']);
        Route::put('customers/profile', [ProfileController::class, 'update']);
        Route::patch('customers/profile', [ProfileController::class, 'update']);
        Route::post('customers/change-password', [ProfileController::class, 'changePassword']);

        Route::get('customers/addresses', [AddressController::class, 'index']);
        Route::post('customers/addresses', [AddressController::class, 'store']);
        Route::put('customers/addresses/{address}', [AddressController::class, 'update']);
        Route::patch('customers/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('customers/addresses/{address}', [AddressController::class, 'destroy']);
        Route::put('customers/addresses/{address}/set-default', [AddressController::class, 'setDefault']);
    });
});
