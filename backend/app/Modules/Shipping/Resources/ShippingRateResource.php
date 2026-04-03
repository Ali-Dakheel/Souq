<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['method']->id,
            'name_en' => $this->resource['method']->name_en,
            'name_ar' => $this->resource['method']->name_ar,
            'carrier' => $this->resource['method']->carrier,
            'type' => $this->resource['method']->type,
            'rate_fils' => $this->resource['rate_fils'],
        ];
    }
}
