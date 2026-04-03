<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Carriers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Shipping\Models\ShippingMethod;

class DHLCarrier implements ShippingCarrierInterface
{
    public function supports(ShippingMethod $method): bool
    {
        return $method->type === 'carrier_api' && $method->carrier === 'dhl';
    }

    public function calculateRate(ShippingMethod $method, Cart $cart): int
    {
        throw new \RuntimeException('DHL carrier API integration not implemented');
    }
}
