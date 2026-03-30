<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Events\PaymentFailed;

class MarkOrderFailedOnPaymentFailed
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function handle(PaymentFailed $event): void
    {
        $order = $event->order;

        // Idempotent: only transition from pending/initiated → failed
        if (! in_array($order->order_status, ['pending', 'initiated'], true)) {
            return;
        }

        $oldStatus = $order->order_status;

        $order->update(['order_status' => 'failed']);

        $this->orderService->recordStatusChange($order, 'failed', 'system', 'Payment failed.', $oldStatus);
    }
}
