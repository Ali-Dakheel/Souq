<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Carriers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Shipping\Models\ShippingMethod;

interface ShippingCarrierInterface
{
    public function supports(ShippingMethod $method): bool;

    public function calculateRate(ShippingMethod $method, Cart $cart): int; // returns fils
}
