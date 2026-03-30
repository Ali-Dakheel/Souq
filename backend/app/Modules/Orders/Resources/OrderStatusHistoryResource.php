<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Modules\Orders\Models\OrderStatusHistory $this */
        return [
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'changed_by' => $this->changed_by,
            'reason'     => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
