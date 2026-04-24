<?php

use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Loyalty\LoyaltyServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Promotions\PromotionServiceProvider;
use App\Modules\Returns\ReturnsServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Shipping\ShippingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use Filament\FilamentServiceProvider;

return [
    CartServiceProvider::class,
    CatalogServiceProvider::class,
    CustomersServiceProvider::class,
    InventoryServiceProvider::class,
    LoyaltyServiceProvider::class,
    NotificationsServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
    PromotionServiceProvider::class,
    ReturnsServiceProvider::class,
    SettingsServiceProvider::class,
    ShippingServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    FilamentServiceProvider::class,
];
