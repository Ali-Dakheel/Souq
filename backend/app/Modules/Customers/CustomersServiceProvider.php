<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Events\AddressAdded;
use App\Modules\Customers\Events\AddressDeleted;
use App\Modules\Customers\Events\AddressUpdated;
use App\Modules\Customers\Events\CustomerProfileUpdated;
use App\Modules\Customers\Events\CustomerRegistered;
use App\Modules\Customers\Events\PasswordReset;
use App\Modules\Customers\Events\PasswordResetRequested;
use App\Modules\Customers\Listeners\LogCustomerEvent;
use App\Modules\Customers\Services\AddressService;
use App\Modules\Customers\Services\AuthService;
use App\Modules\Customers\Services\ProfileService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthService::class);
        $this->app->singleton(ProfileService::class);
        $this->app->singleton(AddressService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        $listener = LogCustomerEvent::class;

        Event::listen(CustomerRegistered::class, [$listener, 'handleCustomerRegistered']);
        Event::listen(CustomerProfileUpdated::class, [$listener, 'handleCustomerProfileUpdated']);
        Event::listen(PasswordResetRequested::class, [$listener, 'handlePasswordResetRequested']);
        Event::listen(PasswordReset::class, [$listener, 'handlePasswordReset']);
        Event::listen(AddressAdded::class, [$listener, 'handleAddressAdded']);
        Event::listen(AddressUpdated::class, [$listener, 'handleAddressUpdated']);
        Event::listen(AddressDeleted::class, [$listener, 'handleAddressDeleted']);
    }
}
