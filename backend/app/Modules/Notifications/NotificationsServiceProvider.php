<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Listeners\SendOrderConfirmationEmail;
use App\Modules\Notifications\Listeners\SendPaymentReceiptEmail;
use App\Modules\Notifications\Listeners\SendShippingUpdateEmail;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Payments\Events\PaymentCaptured;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(OrderPlaced::class, SendOrderConfirmationEmail::class);
        Event::listen(PaymentCaptured::class, SendPaymentReceiptEmail::class);
        Event::listen(OrderFulfilled::class, SendShippingUpdateEmail::class);
    }
}
