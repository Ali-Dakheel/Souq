<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Order $this */
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_status' => $this->order_status,
            'total_fils' => $this->total_fils,
            'item_count' => $this->items_count ?? $this->items->sum('quantity'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
