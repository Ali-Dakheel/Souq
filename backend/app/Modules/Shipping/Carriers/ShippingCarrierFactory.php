<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Carriers;

use App\Modules\Shipping\Models\ShippingMethod;

class ShippingCarrierFactory
{
    /**
     * @param  ShippingCarrierInterface[]  $carriers
     */
    public function __construct(private array $carriers) {}

    public function make(ShippingMethod $method): ShippingCarrierInterface
    {
        foreach ($this->carriers as $carrier) {
            if ($carrier->supports($method)) {
                return $carrier;
            }
        }

        throw new \InvalidArgumentException("Unsupported shipping method type: {$method->type}");
    }
}
