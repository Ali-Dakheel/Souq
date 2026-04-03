<?php

use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Shipping\ShippingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Filament\FilamentServiceProvider;

return [
    AppServiceProvider::class,
    FilamentServiceProvider::class,
    AdminPanelProvider::class,
    CartServiceProvider::class,
    CatalogServiceProvider::class,
    CustomersServiceProvider::class,
    InventoryServiceProvider::class,
    NotificationsServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
    SettingsServiceProvider::class,
    ShippingServiceProvider::class,
];
