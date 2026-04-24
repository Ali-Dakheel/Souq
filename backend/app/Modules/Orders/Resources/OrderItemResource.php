<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use App\Modules\Orders\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'product_name' => $this->product_name,
            'variant_attributes' => $this->variant_attributes,
            'quantity' => $this->quantity,
            'price_fils_per_unit' => $this->price_fils_per_unit,
            'total_fils' => $this->total_fils,
        ];
    }
}
