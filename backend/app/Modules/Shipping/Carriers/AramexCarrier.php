<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Carriers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Shipping\Models\ShippingMethod;

class AramexCarrier implements ShippingCarrierInterface
{
    public function supports(ShippingMethod $method): bool
    {
        return $method->type === 'carrier_api' && $method->carrier === 'aramex';
    }

    public function calculateRate(ShippingMethod $method, Cart $cart): int
    {
        throw new \RuntimeException('Aramex carrier API integration not implemented');
    }
}
