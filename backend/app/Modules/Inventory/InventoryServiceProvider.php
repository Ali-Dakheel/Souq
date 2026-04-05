<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Listeners\RecordMovementOnOrderCancelled;
use App\Modules\Inventory\Listeners\RecordMovementOnOrderPlaced;
use App\Modules\Inventory\Listeners\ReleaseInventoryOnOrderCancelled;
use App\Modules\Inventory\Listeners\ReleaseInventoryOnPaymentFailed;
use App\Modules\Inventory\Listeners\ReserveInventoryOnOrderPlaced;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Payments\Events\PaymentFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        Event::listen(OrderPlaced::class, [ReserveInventoryOnOrderPlaced::class, 'handle']);
        Event::listen(OrderPlaced::class, [RecordMovementOnOrderPlaced::class, 'handle']);
        Event::listen(OrderCancelled::class, [ReleaseInventoryOnOrderCancelled::class, 'handle']);
        Event::listen(OrderCancelled::class, [RecordMovementOnOrderCancelled::class, 'handle']);
        Event::listen(PaymentFailed::class, [ReleaseInventoryOnPaymentFailed::class, 'handle']);
    }
}
