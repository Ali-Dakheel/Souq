<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Orders\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderPlaced $event): void
    {
        Mail::send(new OrderConfirmationMail($event->order));
    }
}
