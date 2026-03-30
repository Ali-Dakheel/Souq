<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Events\PaymentCaptured;

class MarkOrderPaidOnPaymentCaptured
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function handle(PaymentCaptured $event): void
    {
        $order = $event->order;

        // Idempotent: only transition if the order is in an expected state
        if (! in_array($order->order_status, ['pending', 'initiated'], true)) {
            return;
        }

        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => 'paid',
            'paid_at'      => now(),
        ]);

        $this->orderService->recordStatusChange($order, 'paid', 'system', 'Payment captured.', $oldStatus);
    }
}
