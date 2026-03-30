<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_id'          => $this->order_id,
            'tap_transaction_id' => $this->tap_transaction_id,
            'tap_refund_id'     => $this->tap_refund_id,
            'refund_amount_fils' => $this->refund_amount_fils,
            'refund_reason'     => $this->refund_reason,
            'status'            => $this->status,
            'customer_notes'    => $this->customer_notes,
            'admin_notes'       => $this->when($this->admin_notes, $this->admin_notes),
            'created_at'        => $this->created_at?->toISOString(),
            'processed_at'      => $this->processed_at?->toISOString(),
        ];
    }
}
