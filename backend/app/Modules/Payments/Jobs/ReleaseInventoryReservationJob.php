<?php

declare(strict_types=1);

namespace App\Modules\Payments\Jobs;

use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched with a 30-minute delay after payment failure.
 * If the order is still in 'failed' status (user didn't retry),
 * cancels the order and releases inventory reservation.
 *
 * Idempotent: checks current order status before acting.
 */
class ReleaseInventoryReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            return;
        }

        // Only release if still in failed state (user didn't retry and succeed)
        if ($order->order_status !== 'failed') {
            Log::info('Skipping inventory release — order is no longer failed', [
                'order_id' => $this->orderId,
                'status' => $order->order_status,
            ]);

            return;
        }

        $order->loadMissing('items');

        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $orderService = app(OrderService::class);
        $orderService->recordStatusChange($order, 'cancelled', 'system', 'Payment retry window expired.', $oldStatus);

        $eventItems = $order->items
            ->filter(fn ($i) => $i->variant_id !== null)
            ->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity])
            ->values()
            ->toArray();

        OrderCancelled::dispatch($order, $eventItems, 'Payment retry window expired.');
    }
}
