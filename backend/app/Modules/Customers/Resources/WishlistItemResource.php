<?php

declare(strict_types=1);

namespace App\Modules\Customers\Resources;

use App\Modules\Customers\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WishlistItem */
class WishlistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = [];
        if ($this->relationLoaded('variant')) {
            $variant = [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'attributes' => $this->variant->attributes,
                'price_fils' => $this->variant->price_fils,
                'is_available' => $this->variant->is_available,
                'effective_price_fils' => $this->variant->effective_price_fils,
            ];

            if ($this->variant->relationLoaded('product')) {
                $variant['product'] = [
                    'id' => $this->variant->product->id,
                    'name_en' => $this->variant->product->name['en'] ?? '',
                    'name_ar' => $this->variant->product->name['ar'] ?? '',
                    'base_price_fils' => $this->variant->product->base_price_fils,
                ];
            }
        }

        return [
            'id' => $this->id,
            'wishlist_id' => $this->wishlist_id,
            'variant_id' => $this->variant_id,
            'added_at' => $this->added_at?->toIso8601String(),
            'variant' => $variant !== [] ? $variant : null,
        ];
    }
}
