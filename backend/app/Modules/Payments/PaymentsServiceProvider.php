<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Payments\Jobs\CheckStalePaymentsJob;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\RefundService;
use App\Modules\Payments\Services\TapApiService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TapApiService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(RefundService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Schedule stale payment checker every 15 minutes
        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);
            $schedule->job(new CheckStalePaymentsJob)->everyFifteenMinutes();
        });
    }
}
