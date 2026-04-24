<?php

declare(strict_types=1);

namespace App\Modules\Customers\Resources;

use App\Modules\Customers\Models\VariantGroupPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VariantGroupPrice */
class VariantGroupPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'customer_group_id' => $this->customer_group_id,
            'price_fils' => $this->price_fils,
            'compare_at_price_fils' => $this->compare_at_price_fils,
        ];
    }
}
