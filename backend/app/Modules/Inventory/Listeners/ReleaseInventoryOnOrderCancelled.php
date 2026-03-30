<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Listeners;

use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Orders\Events\OrderCancelled;
use Illuminate\Support\Facades\DB;

class ReleaseInventoryOnOrderCancelled
{
    public function handle(OrderCancelled $event): void
    {
        DB::transaction(function () use ($event) {
            foreach ($event->items as $item) {
                $inventory = InventoryItem::where('variant_id', $item['variant_id'])
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    continue;
                }

                $decrement = min($item['quantity'], $inventory->quantity_reserved);
                if ($decrement > 0) {
                    $inventory->decrement('quantity_reserved', $decrement);
                }
            }
        });
    }
}
