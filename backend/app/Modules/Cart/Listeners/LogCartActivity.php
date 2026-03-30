<?php

declare(strict_types=1);

namespace App\Modules\Cart\Listeners;

use App\Modules\Cart\Events\CartAbandoned;
use App\Modules\Cart\Events\CartItemAdded;
use App\Modules\Cart\Events\CartItemRemoved;
use App\Modules\Cart\Events\CartMerged;
use App\Modules\Cart\Events\CouponApplied;
use App\Modules\Cart\Events\CouponRemoved;
use Illuminate\Support\Facades\Log;

class LogCartActivity
{
    public function handleItemAdded(CartItemAdded $event): void
    {
        Log::info('cart.item_added', [
            'cart_id' => $event->cart->id,
            'variant_id' => $event->cartItem->variant_id,
            'quantity' => $event->cartItem->quantity,
        ]);
    }

    public function handleItemRemoved(CartItemRemoved $event): void
    {
        Log::info('cart.item_removed', [
            'cart_id' => $event->cart->id,
            'variant_id' => $event->variantId,
            'quantity' => $event->quantity,
        ]);
    }

    public function handleCouponApplied(CouponApplied $event): void
    {
        Log::info('cart.coupon_applied', [
            'cart_id' => $event->cart->id,
            'coupon_code' => $event->coupon->code,
            'discount_fils' => $event->discountFils,
        ]);
    }

    public function handleCouponRemoved(CouponRemoved $event): void
    {
        Log::info('cart.coupon_removed', [
            'cart_id' => $event->cart->id,
            'coupon_code' => $event->removedCode,
        ]);
    }

    public function handleCartMerged(CartMerged $event): void
    {
        Log::info('cart.merged', [
            'user_id' => $event->user->id,
            'cart_id' => $event->userCart->id,
            'guest_session_id' => $event->guestSessionId,
            'items_added' => $event->itemsAdded,
            'items_updated' => $event->itemsUpdated,
        ]);
    }

    public function handleCartAbandoned(CartAbandoned $event): void
    {
        Log::info('cart.abandoned', [
            'cart_id' => $event->cart->id,
            'abandonment_id' => $event->abandonment->id,
        ]);
    }
}
