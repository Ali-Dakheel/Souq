<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderShippingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'method_name_en' => $this->method_name_en,
            'method_name_ar' => $this->method_name_ar,
            'rate_fils' => $this->rate_fils,
            'tracking_number' => $this->tracking_number,
        ];
    }
}
