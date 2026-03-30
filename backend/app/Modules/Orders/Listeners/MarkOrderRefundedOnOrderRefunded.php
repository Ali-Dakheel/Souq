<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Events\OrderRefunded;

class MarkOrderRefundedOnOrderRefunded
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function handle(OrderRefunded $event): void
    {
        $order = $event->order;

        // Idempotent: only transition from paid → refunded
        if ($order->order_status !== 'paid') {
            return;
        }

        $order->update(['order_status' => 'refunded']);

        $this->orderService->recordStatusChange($order, 'refunded', 'system', 'Refund processed.');
    }
}
