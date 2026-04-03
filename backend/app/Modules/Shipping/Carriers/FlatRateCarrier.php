<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Carriers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Shipping\Models\ShippingMethod;

class FlatRateCarrier implements ShippingCarrierInterface
{
    public function supports(ShippingMethod $method): bool
    {
        return $method->type === 'flat_rate';
    }

    public function calculateRate(ShippingMethod $method, Cart $cart): int
    {
        return (int) ($method->rate_fils ?? 0);
    }
}
