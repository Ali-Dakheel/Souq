<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Orders\Models\Order;
use App\Modules\Shipping\Carriers\ShippingCarrierFactory;
use App\Modules\Shipping\Models\OrderShipping;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use Illuminate\Support\Facades\Cache;

class ShippingService
{
    public function __construct(private ShippingCarrierFactory $factory) {}

    /**
     * Check if cart contains only virtual products.
     * Returns true only if cart has items AND all items are virtual.
     * Returns false if cart is empty (safety: empty cart is not virtual for shipping purposes).
     */
    public function isVirtualCart(Cart $cart): bool
    {
        // Load cart items with their variant and variant's product
        $cart->load('items.variant.product');

        // Empty cart is not virtual
        if ($cart->items->isEmpty()) {
            return false;
        }

        // Check if all items have virtual product type
        return $cart->items->every(fn ($item) => $item->variant?->product?->product_type === 'virtual');
    }

    /**
     * Resolve the shipping zone for a customer address.
     * Since CustomerAddress is Bahrain-only (no country field), all addresses use 'BH'.
     * Checks if 'BH' is in any active zone's countries array.
     */
    public function resolveZoneForAddress(CustomerAddress $address): ?ShippingZone
    {
        $country = 'BH'; // CustomerAddress is Bahrain-only — all addresses are 'BH'
        $zones = ShippingZone::where('is_active', true)->get();

        foreach ($zones as $zone) {
            if (in_array($country, $zone->countries ?? [], true)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Get available shipping rates for a cart and address.
     * Returns cached array of ['method' => ShippingMethod, 'rate_fils' => int].
     * Returns empty array if cart is virtual or no zone found for address.
     * Silently skips unsupported method types.
     *
     * @return list<array{method: ShippingMethod, rate_fils: int}>
     */
    public function getAvailableRates(Cart $cart, CustomerAddress $address): array
    {
        $cacheKey = "shipping_rates_{$cart->id}_{$address->id}";
        $ttl = 600; // 10 minutes

        return Cache::remember($cacheKey, $ttl, function () use ($cart, $address): array {
            // Virtual carts have no shipping
            if ($this->isVirtualCart($cart)) {
                return [];
            }

            // Resolve zone for address
            $zone = $this->resolveZoneForAddress($address);
            if ($zone === null) {
                return [];
            }

            // Load cart items for rate calculation
            $cart->load('items');

            // Get active methods for the zone
            $methods = $zone->methods()->where('is_active', true)->get();

            $rates = [];
            foreach ($methods as $method) {
                try {
                    $rate = $this->factory->make($method)->calculateRate($method, $cart);
                    $rates[] = [
                        'method' => $method,
                        'rate_fils' => $rate,
                    ];
                } catch (\InvalidArgumentException $e) {
                    // Silently skip unsupported method types
                    continue;
                }
            }

            return $rates;
        });
    }

    /**
     * Validate that a shipping method is available for a cart and address.
     * Throws \InvalidArgumentException if method not available.
     */
    public function validateShippingMethodForCart(
        Cart $cart,
        int $shippingMethodId,
        CustomerAddress $shippingAddress
    ): ShippingMethod {
        $method = ShippingMethod::with('zone')->findOrFail($shippingMethodId);

        // Check if method is active
        if (! $method->is_active) {
            throw new \InvalidArgumentException('Shipping method is not available');
        }

        // Resolve zone for address
        $zone = $this->resolveZoneForAddress($shippingAddress);
        if ($zone === null || $zone->id !== $method->zone->id) {
            throw new \InvalidArgumentException('Shipping method is not available for your delivery address');
        }

        return $method;
    }

    /**
     * Attach shipping to order.
     * Idempotent: returns existing shipping if already present.
     */
    public function attachShippingToOrder(
        Order $order,
        ShippingMethod $method,
        int $rateFils
    ): OrderShipping {
        // Idempotent guard: return existing if present (use DB query, not in-memory relation)
        $existing = $order->shipping()->first();
        if ($existing !== null) {
            return $existing;
        }

        return OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $method->id,
            'carrier' => $method->carrier,
            'method_name_en' => $method->name_en,
            'method_name_ar' => $method->name_ar,
            'rate_fils' => $rateFils,
        ]);
    }
}
