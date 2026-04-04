<?php

declare(strict_types=1);

namespace App\Modules\Promotions;

use App\Modules\Promotions\Services\PromotionService;
use Illuminate\Support\ServiceProvider;

class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PromotionService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
