<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when admin approves a refund and Tap processes it successfully.
 * Listened to by: Notifications (customer email).
 */
class RefundApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Refund $refund,
    ) {}
}
