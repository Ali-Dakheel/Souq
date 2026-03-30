<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an order is marked as fulfilled (shipped).
 * Listened to by: Notifications (shipping update email).
 *
 * NOTE: No trigger exists yet — fired by Filament admin in Phase 3.
 */
class OrderFulfilled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
