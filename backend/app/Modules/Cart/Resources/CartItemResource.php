<?php

declare(strict_types=1);

namespace App\Modules\Cart\Resources;

use App\Modules\Cart\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $variant = $this->variant;

        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'quantity' => $this->quantity,
            'price_fils_snapshot' => $this->price_fils_snapshot,
            'price_current_fils' => $this->current_price_fils,
            'price_changed' => $this->price_changed,
            'line_total_fils' => $this->line_total_fils,
            'variant' => $this->when($variant !== null, fn () => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'attributes' => $variant->attributes,
                'price_fils' => $variant->effective_price_fils,
                'is_available' => $variant->is_available,
                'product' => $this->when(
                    $variant->relationLoaded('product'),
                    fn () => [
                        'id' => $variant->product->id,
                        'name' => $variant->product->name,
                        'slug' => $variant->product->slug,
                        'images' => $variant->product->images ?? [],
                    ]
                ),
            ]),
        ];
    }
}
