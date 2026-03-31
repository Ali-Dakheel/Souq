<?php

declare(strict_types=1);

namespace App\Modules\Cart\Resources;

use App\Modules\Cart\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCouponResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Coupon $this */
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type, // percentage | fixed_fils
            'discount_value' => $this->discount_value,
            'minimum_order_amount_fils' => $this->minimum_order_amount_fils,
            'maximum_discount_fils' => $this->maximum_discount_fils,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'applicable_to' => $this->applicable_to,
        ];
    }
}
