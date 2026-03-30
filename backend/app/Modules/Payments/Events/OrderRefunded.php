<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the Payments module after a refund is processed.
 * Listened to by: Orders (update status), Inventory (return stock).
 *
 * NOTE: This is a stub — implementation lives in the Payments module (Phase 2.5).
 */
class OrderRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly int $refundFils,
    ) {}
}
