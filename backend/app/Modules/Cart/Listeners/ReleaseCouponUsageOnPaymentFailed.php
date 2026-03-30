<?php

declare(strict_types=1);

namespace App\Modules\Cart\Listeners;

use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Payments\Events\PaymentFailed;

class ReleaseCouponUsageOnPaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        $order = $event->order;

        if ($order->coupon_code === null) {
            return;
        }

        $coupon = Coupon::where('code', $order->coupon_code)->first();

        if ($coupon === null) {
            return;
        }

        CouponUsage::where('coupon_id', $coupon->id)
            ->where('order_id', $order->id)
            ->delete();
    }
}
