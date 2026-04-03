<?php

declare(strict_types=1);

namespace App\Modules\Shipping;

use App\Modules\Shipping\Carriers\AramexCarrier;
use App\Modules\Shipping\Carriers\DHLCarrier;
use App\Modules\Shipping\Carriers\FlatRateCarrier;
use App\Modules\Shipping\Carriers\FreeThresholdCarrier;
use App\Modules\Shipping\Carriers\ShippingCarrierFactory;
use App\Modules\Shipping\Services\ShippingService;
use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShippingCarrierFactory::class, function () {
            return new ShippingCarrierFactory([
                new FlatRateCarrier,
                new FreeThresholdCarrier,
                new AramexCarrier,
                new DHLCarrier,
            ]);
        });

        $this->app->singleton(ShippingService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
