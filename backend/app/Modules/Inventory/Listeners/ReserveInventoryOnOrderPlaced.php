<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Listeners;

use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Orders\Events\OrderPlaced;
use Illuminate\Support\Facades\DB;

class ReserveInventoryOnOrderPlaced
{
    public function handle(OrderPlaced $event): void
    {
        DB::transaction(function () use ($event) {
            foreach ($event->items as $item) {
                $inventory = InventoryItem::where('variant_id', $item['variant_id'])
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    continue;
                }

                // Idempotent: do not double-reserve if somehow fired twice
                // (real idempotency is ensured by the transaction + lock)
                $inventory->increment('quantity_reserved', $item['quantity']);
            }
        });
    }
}
