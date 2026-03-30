<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired immediately after an order is created from cart checkout.
 * Listened to by: Inventory (reserve stock), Notifications (confirm email), Cart (record coupon usage).
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    /**
     * @param Order $order
     * @param array<int, array{variant_id: int, quantity: int}> $items
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $items,
    ) {}
}
