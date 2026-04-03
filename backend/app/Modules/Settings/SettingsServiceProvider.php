<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Modules\Settings\Services\StoreSettingsService;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoreSettingsService::class);
    }

    public function boot(): void
    {
        // No routes — admin-only via Filament
    }
}
