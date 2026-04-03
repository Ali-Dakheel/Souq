<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use App\Modules\Orders\Models\Order;
use App\Modules\Shipping\Resources\OrderShippingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Order $this */
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_status' => $this->order_status,
            'payment_method' => $this->payment_method,
            'subtotal_fils' => $this->subtotal_fils,
            'coupon_code' => $this->coupon_code,
            'coupon_discount_fils' => $this->coupon_discount_fils,
            'vat_fils' => $this->vat_fils,
            'delivery_fee_fils' => $this->delivery_fee_fils,
            'total_fils' => $this->total_fils,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'shipping_address' => $this->when(
                $this->shipping_address_snapshot !== null,
                fn () => $this->shipping_address_snapshot,
            ),
            'billing_address' => $this->when(
                $this->billing_address_snapshot !== null,
                fn () => $this->billing_address_snapshot,
            ),
            'shipping' => new OrderShippingResource($this->whenLoaded('shipping')),
        ];
    }
}
