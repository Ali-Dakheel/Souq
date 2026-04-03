<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the Orders module when a COD order's cash payment is confirmed by admin.
 * Listened to by: Notifications (send payment receipt email to customer).
 */
class CODCollected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
