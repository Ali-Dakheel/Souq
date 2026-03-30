<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when admin rejects a refund request.
 * Listened to by: Notifications (customer email).
 */
class RefundRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Refund $refund,
    ) {}
}
