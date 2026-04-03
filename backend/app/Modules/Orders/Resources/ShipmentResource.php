<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'notes' => $this->notes,
            'items' => ShipmentItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
