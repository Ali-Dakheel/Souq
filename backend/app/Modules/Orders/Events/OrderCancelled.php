<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer or admin cancels an order.
 * Listened to by: Inventory (release reservation), Notifications (cancellation email stub).
 */
class OrderCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array{variant_id: int, quantity: int}>  $items
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $items,
        public readonly ?string $reason = null,
    ) {}
}
