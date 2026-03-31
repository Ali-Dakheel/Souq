<?php

declare(strict_types=1);

namespace App\Modules\Cart\Resources;

use App\Modules\Cart\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly array $totals = [],
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Cart $this */
        $coupon = $this->coupon_code ? $this->coupon()->first() : null;

        return [
            'id' => $this->id,
            'item_count' => $this->items->sum('quantity'),
            'items' => CartItemResource::collection($this->items),
            'coupon_code' => $this->coupon_code,
            'coupon' => $this->when($coupon !== null, fn () => [
                'code' => $coupon->code,
                'description' => $coupon->description,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
            ]),
            'subtotal_fils' => $this->totals['subtotal_fils'] ?? 0,
            'discount_fils' => $this->totals['discount_fils'] ?? 0,
            'vat_fils' => $this->totals['vat_fils'] ?? 0,
            'total_fils' => $this->totals['total_fils'] ?? 0,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'has_price_changes' => $this->items->contains(fn ($item) => $item->price_changed),
        ];
    }
}
