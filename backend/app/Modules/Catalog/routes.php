<?php

use App\Modules\Catalog\Controllers\AttributeController;
use App\Modules\Catalog\Controllers\CategoryController;
use App\Modules\Catalog\Controllers\ProductController;
use App\Modules\Catalog\Controllers\VariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('api')->group(function () {
    // Categories — tree listing + CRUD
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::patch('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    Route::get('categories/{category}/products', [CategoryController::class, 'products']);

    // Products — filterable listing + CRUD
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::patch('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::get('products/{product}/variants', [ProductController::class, 'variants']);

    // Variants — nested under products
    Route::post('products/{product}/variants', [VariantController::class, 'store']);
    Route::get('products/{product}/variants/{variant}', [VariantController::class, 'show']);
    Route::put('products/{product}/variants/{variant}', [VariantController::class, 'update']);
    Route::patch('products/{product}/variants/{variant}', [VariantController::class, 'update']);
    Route::delete('products/{product}/variants/{variant}', [VariantController::class, 'destroy']);

    // Attributes + values
    Route::get('attributes', [AttributeController::class, 'index']);
    Route::post('attributes', [AttributeController::class, 'store']);
    Route::get('attributes/{attribute}', [AttributeController::class, 'show']);
    Route::put('attributes/{attribute}', [AttributeController::class, 'update']);
    Route::patch('attributes/{attribute}', [AttributeController::class, 'update']);
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy']);
    Route::post('attributes/{attribute}/values', [AttributeController::class, 'storeValue']);
    Route::put('attributes/{attribute}/values/{value}', [AttributeController::class, 'updateValue']);
    Route::patch('attributes/{attribute}/values/{value}', [AttributeController::class, 'updateValue']);
    Route::delete('attributes/{attribute}/values/{value}', [AttributeController::class, 'destroyValue']);

    // Bundle options and downloadable links (authenticated)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('products/{product}/bundle-options', [ProductController::class, 'storeBundleOption']);
        Route::post('products/{product}/bundle-options/{option}/products', [ProductController::class, 'addBundleOptionProduct']);
        Route::post('products/{product}/downloadable-links', [ProductController::class, 'storeDownloadableLink']);
    });
});
