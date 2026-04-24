<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use App\Modules\Orders\Models\ShipmentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShipmentItem */
class ShipmentItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'order_item_id' => $this->order_item_id,
            'quantity_shipped' => $this->quantity_shipped,
        ];
    }
}
