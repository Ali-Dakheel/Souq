<?php

declare(strict_types=1);

namespace App\Modules\Cart\Listeners;

use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Orders\Events\OrderPlaced;

class RecordCouponUsageOnOrderPlaced
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        if ($order->coupon_code === null || $order->coupon_discount_fils === 0) {
            return;
        }

        $coupon = Coupon::where('code', $order->coupon_code)->first();

        if ($coupon === null) {
            return;
        }

        // Idempotent: do not double-record if event fires twice
        $exists = CouponUsage::where('coupon_id', $coupon->id)
            ->where('order_id', $order->id)
            ->exists();

        if ($exists) {
            return;
        }

        CouponUsage::create([
            'coupon_id'            => $coupon->id,
            'order_id'             => $order->id,
            'user_id'              => $order->user_id,
            'discount_amount_fils' => $order->coupon_discount_fils,
            'used_at'              => now(),
        ]);
    }
}
