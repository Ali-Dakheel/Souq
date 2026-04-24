<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TapTransaction */
class TapTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'tap_charge_id' => $this->tap_charge_id,
            'amount_fils' => $this->amount_fils,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'attempt_number' => $this->attempt_number,
            'failure_reason' => $this->when($this->status === 'failed', $this->failure_reason),
            'redirect_url' => $this->when($this->status === 'initiated', $this->redirect_url),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
