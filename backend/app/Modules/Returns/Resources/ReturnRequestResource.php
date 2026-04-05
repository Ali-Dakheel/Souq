<?php

declare(strict_types=1);

namespace App\Modules\Returns\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'admin_notes' => $this->admin_notes,
            'resolution' => $this->resolution,
            'resolution_amount_fils' => $this->resolution_amount_fils,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'quantity_returned' => $item->quantity_returned,
                'condition' => $item->condition,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
