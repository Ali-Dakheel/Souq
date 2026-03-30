<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer submits a refund request.
 * Listened to by: Notifications (admin alert).
 */
class RefundRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Refund $refund,
    ) {}
}
