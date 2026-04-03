<?php

use App\Modules\Customers\Controllers\AddressController;
use App\Modules\Customers\Controllers\AuthController;
use App\Modules\Customers\Controllers\CustomerGroupController;
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

    // Customer Groups — public read
    Route::get('groups', [CustomerGroupController::class, 'index']);
    Route::get('groups/{group}', [CustomerGroupController::class, 'show']);

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

        // Customer Groups — admin write (auth required)
        Route::post('groups', [CustomerGroupController::class, 'store']);
        Route::put('groups/{group}', [CustomerGroupController::class, 'update']);
        Route::patch('groups/{group}', [CustomerGroupController::class, 'update']);
        Route::delete('groups/{group}', [CustomerGroupController::class, 'destroy']);
        Route::post('groups/{group}/prices', [CustomerGroupController::class, 'setPrice']);
        Route::delete('groups/{group}/prices/{variant}', [CustomerGroupController::class, 'removePrice']);
    });
});
