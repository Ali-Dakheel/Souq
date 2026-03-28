<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    CustomersServiceProvider::class,
    InventoryServiceProvider::class,
    NotificationsServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
];
