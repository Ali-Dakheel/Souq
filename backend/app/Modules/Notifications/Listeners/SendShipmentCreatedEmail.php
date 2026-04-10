<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\ShipmentCreatedMail;
use App\Modules\Orders\Events\ShipmentCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendShipmentCreatedEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(ShipmentCreated $event): void
    {
        Mail::queue(new ShipmentCreatedMail($event->shipment, $event->order));
    }
}
