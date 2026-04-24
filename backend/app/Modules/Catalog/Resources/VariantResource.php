<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Variant */
class VariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'attributes' => $this->attributes,
            'price_fils' => $this->price_fils,
            'effective_price_fils' => $this->effective_price_fils,
            'is_available' => $this->is_available,
            'sort_order' => $this->sort_order,
            'inventory' => $this->whenLoaded('inventory', function (): ?array {
                $inv = $this->inventory;
                if (! $inv instanceof InventoryItem) {
                    return null;
                }

                return [
                    'quantity_available' => $inv->quantity_available,
                    'quantity_reserved' => $inv->quantity_reserved,
                    'quantity_on_sale' => $inv->quantity_on_sale,
                    'low_stock_threshold' => $inv->low_stock_threshold,
                    'is_low_stock' => $inv->quantity_available > 0
                        && $inv->quantity_available <= $inv->low_stock_threshold,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
