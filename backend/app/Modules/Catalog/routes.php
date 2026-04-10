<?php

use App\Modules\Catalog\Controllers\AttributeController;
use App\Modules\Catalog\Controllers\CategoryController;
use App\Modules\Catalog\Controllers\DownloadController;
use App\Modules\Catalog\Controllers\ProductController;
use App\Modules\Catalog\Controllers\VariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('api')->group(function () {
    // Categories — public read
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('categories/{category}/products', [CategoryController::class, 'products']);

    // Products — public read
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products/{product}/variants', [ProductController::class, 'variants']);
    Route::get('products/{product}/variants/{variant}', [VariantController::class, 'show']);

    // Search — Meilisearch full-text
    Route::get('search', [ProductController::class, 'search'])->middleware('throttle:120,1');

    // Product compare — stateless attribute matrix (read-only, public)
    Route::post('compare', [ProductController::class, 'compare']);

    // Attributes + values — public read
    Route::get('attributes', [AttributeController::class, 'index']);
    Route::get('attributes/{attribute}', [AttributeController::class, 'show']);

    // All write endpoints require authentication
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        // Categories — write
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::patch('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // Products — write
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::patch('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        // Variants — write
        Route::post('products/{product}/variants', [VariantController::class, 'store']);
        Route::put('products/{product}/variants/{variant}', [VariantController::class, 'update']);
        Route::patch('products/{product}/variants/{variant}', [VariantController::class, 'update']);
        Route::delete('products/{product}/variants/{variant}', [VariantController::class, 'destroy']);

        // Attributes + values — write
        Route::post('attributes', [AttributeController::class, 'store']);
        Route::put('attributes/{attribute}', [AttributeController::class, 'update']);
        Route::patch('attributes/{attribute}', [AttributeController::class, 'update']);
        Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy']);
        Route::post('attributes/{attribute}/values', [AttributeController::class, 'storeValue']);
        Route::put('attributes/{attribute}/values/{value}', [AttributeController::class, 'updateValue']);
        Route::patch('attributes/{attribute}/values/{value}', [AttributeController::class, 'updateValue']);
        Route::delete('attributes/{attribute}/values/{value}', [AttributeController::class, 'destroyValue']);

        // Bundle options and downloadable links
        Route::post('products/{product}/bundle-options', [ProductController::class, 'storeBundleOption']);
        Route::post('products/{product}/bundle-options/{option}/products', [ProductController::class, 'addBundleOptionProduct']);
        Route::post('products/{product}/downloadable-links', [ProductController::class, 'storeDownloadableLink']);
        Route::get('downloads/{token}', [DownloadController::class, 'download']);
    });
});
