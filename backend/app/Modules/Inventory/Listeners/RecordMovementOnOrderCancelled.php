<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Listeners;

use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Orders\Events\OrderCancelled;

class RecordMovementOnOrderCancelled
{
    public function __construct(private InventoryMovementService $movementService) {}

    public function handle(OrderCancelled $event): void
    {
        foreach ($event->items as $item) {
            $inventory = InventoryItem::where('variant_id', $item['variant_id'])->first();

            if ($inventory === null) {
                continue;
            }

            $quantityAfter = $inventory->quantity_available - $inventory->quantity_reserved;

            $this->movementService->record(
                variantId: $item['variant_id'],
                type: 'release',
                delta: $item['quantity'],
                quantityAfter: $quantityAfter,
                referenceType: 'order',
                referenceId: $event->order->id,
            );
        }
    }
}
