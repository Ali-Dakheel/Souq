<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Services\AttributeService;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(AttributeService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
