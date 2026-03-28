<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'inventory' => $this->whenLoaded('inventory', fn () => [
                'quantity_available' => $this->inventory?->quantity_available,
                'quantity_reserved' => $this->inventory?->quantity_reserved,
                'quantity_on_sale' => $this->inventory?->quantity_on_sale,
                'low_stock_threshold' => $this->inventory?->low_stock_threshold,
                'is_low_stock' => $this->inventory !== null
                    && $this->inventory->quantity_available > 0
                    && $this->inventory->quantity_available <= $this->inventory->low_stock_threshold,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
