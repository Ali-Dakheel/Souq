<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Carriers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Shipping\Models\ShippingMethod;

class FreeThresholdCarrier implements ShippingCarrierInterface
{
    public function supports(ShippingMethod $method): bool
    {
        return $method->type === 'free_threshold';
    }

    public function calculateRate(ShippingMethod $method, Cart $cart): int
    {
        // Compute cart item subtotal from already-loaded items
        $subtotal = 0;
        foreach ($cart->items as $item) {
            $subtotal += (int) ($item->price_fils_snapshot * $item->quantity);
        }

        // If subtotal >= threshold, return 0; else return the rate
        if ($subtotal >= ($method->free_threshold_fils ?? 0)) {
            return 0;
        }

        return (int) ($method->rate_fils ?? 0);
    }
}
