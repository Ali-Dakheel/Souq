<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use App\Modules\Catalog\Models\BundleOptionProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BundleOptionProduct */
class BundleOptionProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bundle_option_id' => $this->bundle_option_id,
            'product_id' => $this->product_id,
            'default_quantity' => $this->default_quantity,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'price_override_fils' => $this->price_override_fils,
            'sort_order' => $this->sort_order,
        ];
    }
}
