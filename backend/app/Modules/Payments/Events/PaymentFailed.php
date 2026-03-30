<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the Payments module after Tap Payments reports a failed charge.
 * Listened to by: Orders (mark failed), Inventory (release reservation), Cart (release coupon usage).
 *
 * NOTE: This is a stub — implementation lives in the Payments module (Phase 2.5).
 */
class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
