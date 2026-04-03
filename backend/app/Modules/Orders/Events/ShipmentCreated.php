<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Order $order,
    ) {}
}
