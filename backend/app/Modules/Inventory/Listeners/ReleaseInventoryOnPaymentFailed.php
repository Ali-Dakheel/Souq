<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Listeners;

use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Payments\Events\PaymentFailed;
use Illuminate\Support\Facades\DB;

class ReleaseInventoryOnPaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        DB::transaction(function () use ($event) {
            $order = $event->order;
            $order->loadMissing('items');

            foreach ($order->items as $orderItem) {
                if ($orderItem->variant_id === null) {
                    continue;
                }

                $inventory = InventoryItem::where('variant_id', $orderItem->variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    continue;
                }

                $decrement = min($orderItem->quantity, $inventory->quantity_reserved);
                if ($decrement > 0) {
                    $inventory->decrement('quantity_reserved', $decrement);
                }
            }
        });
    }
}
