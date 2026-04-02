<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\ShippingUpdateMail;
use App\Modules\Orders\Events\OrderFulfilled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendShippingUpdateEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderFulfilled $event): void
    {
        Mail::send(new ShippingUpdateMail($event->order));
    }
}
