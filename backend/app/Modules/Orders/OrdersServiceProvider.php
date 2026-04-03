<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Orders\Listeners\GenerateInvoiceOnPaymentCaptured;
use App\Modules\Orders\Listeners\MarkOrderFailedOnPaymentFailed;
use App\Modules\Orders\Listeners\MarkOrderPaidOnPaymentCaptured;
use App\Modules\Orders\Listeners\MarkOrderRefundedOnOrderRefunded;
use App\Modules\Orders\Services\InvoiceService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\ShipmentService;
use App\Modules\Payments\Events\OrderRefunded;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Events\PaymentFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(ShipmentService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        Event::listen(PaymentCaptured::class, [MarkOrderPaidOnPaymentCaptured::class, 'handle']);
        Event::listen(PaymentCaptured::class, [GenerateInvoiceOnPaymentCaptured::class, 'handle']);
        Event::listen(PaymentFailed::class, [MarkOrderFailedOnPaymentFailed::class, 'handle']);
        Event::listen(OrderRefunded::class, [MarkOrderRefundedOnOrderRefunded::class, 'handle']);
    }
}
