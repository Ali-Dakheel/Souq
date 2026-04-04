# 3D.1 Shipping Module — Continuation Context

## Session Handoff

**Branch:** `feat/3d1-shipping`
**Worktree:** `C:/Users/User/Desktop/souq/Souq/.worktrees/feat-3d1-shipping`
**Status:** Tasks 1–3 complete, 338/338 tests passing

---

## Environment

```bash
# PHP binary
/c/Users/User/.config/herd/bin/php84/php

# Composer
/c/Users/User/.config/herd/bin/php84/php /c/Users/User/.config/herd/bin/composer.phar

# Run tests (from worktree root)
cd backend && /c/Users/User/.config/herd/bin/php84/php artisan test --compact

# Working directory
C:/Users/User/Desktop/souq/Souq/.worktrees/feat-3d1-shipping
```

---

## What's Done (Tasks 1–3)

### Task 1 — DB Migrations + Models
- `database/migrations/..._create_shipping_zones_table.php` — id, name_en, name_ar, countries (jsonb), regions (jsonb nullable), is_active (bool default true), sort_order (smallint default 0)
- `database/migrations/..._create_shipping_methods_table.php` — id, shipping_zone_id (FK), carrier (string), name_en, name_ar, type (string: flat_rate/free_threshold/carrier_api), rate_fils (int nullable), free_threshold_fils (int nullable), is_active (bool), sort_order (smallint), config (jsonb nullable)
- `database/migrations/..._create_order_shipping_table.php` — id, order_id (FK), shipping_method_id (FK nullable), carrier, method_name_en, method_name_ar, rate_fils (int), tracking_number (nullable)
- `app/Modules/Shipping/Models/ShippingZone.php`
- `app/Modules/Shipping/Models/ShippingMethod.php`
- `app/Modules/Shipping/Models/OrderShipping.php` — has `protected $table = 'order_shipping'`

### Task 2 — Carrier Interface + Factory
- `app/Modules/Shipping/Carriers/ShippingCarrierInterface.php`
- `app/Modules/Shipping/Carriers/FlatRateCarrier.php`
- `app/Modules/Shipping/Carriers/FreeThresholdCarrier.php`
- `app/Modules/Shipping/Carriers/AramexCarrier.php` (stub — throws RuntimeException)
- `app/Modules/Shipping/Carriers/DHLCarrier.php` (stub — throws RuntimeException)
- `app/Modules/Shipping/Carriers/ShippingCarrierFactory.php`
- `app/Modules/Shipping/Services/ShippingService.php`

### Task 3 — Controller + Routes + OrderService Integration
- `app/Modules/Shipping/Requests/ShippingRatesRequest.php`
- `app/Modules/Shipping/Controllers/ShippingController.php`
- `app/Modules/Shipping/Resources/ShippingRateResource.php` (placeholder — Task 4 finalizes)
- `app/Modules/Shipping/ShippingServiceProvider.php`
- `app/Modules/Shipping/routes.php`
- `app/Modules/Orders/Services/OrderService.php` — updated with ShippingService injection + shippingMethodId param
- `app/Modules/Orders/Controllers/OrderController.php` — passes shipping_method_id
- `app/Modules/Orders/Requests/CheckoutRequest.php` — added shipping_method_id validation
- `backend/bootstrap/providers.php` — added ShippingServiceProvider
- Existing tests updated to use `product_type: 'virtual'` to bypass shipping validation

---

## Critical Architecture Notes

### CustomerAddress has NO `country` field
Bahrain-only store. `resolveZoneForAddress()` hardcodes `$country = 'BH'`. Never use `$address->governorate` for ISO-2 country code matching.

### OrderShipping table name
Eloquent would auto-pluralize to `order_shippings`. Model has `protected $table = 'order_shipping'`.

### Virtual cart bypass
If ALL cart items have `product_type === 'virtual'`, shipping is skipped entirely (`delivery_fee_fils = 0`, no shipping_method_id required).

### Shipping rate computation
Must happen BEFORE the DB transaction so `delivery_fee_fils` and `total_fils` are correct when the order row is inserted. `attachShippingToOrder()` is called AFTER the transaction.

### Filament version
v5 (NOT v3) — ALL form methods use `Schema $schema: Schema` from `Filament\Schemas\Schema`. Use `$schema->schema([...])` NOT `->components([...])`.

### Rate cache
Key: `"shipping_rates_{$cart->id}_{$address->id}"`, TTL: 600 seconds.

### idempotency in attachShippingToOrder
Uses `$order->shipping()->first()` (DB query), NOT `$order->shipping` (in-memory relation).

---

## Current File State

### `app/Modules/Shipping/Services/ShippingService.php`
```php
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
use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function __construct(
        private readonly ShippingCarrierFactory $carrierFactory,
    ) {}

    public function resolveZoneForAddress(CustomerAddress $address): ?ShippingZone
    {
        $country = 'BH'; // CustomerAddress is Bahrain-only
        $zones = ShippingZone::where('is_active', true)->get();
        foreach ($zones as $zone) {
            if (in_array($country, $zone->countries ?? [], true)) {
                return $zone;
            }
        }
        return null;
    }

    /** @return array<int, array{method: ShippingMethod, rate_fils: int}> */
    public function getAvailableRates(Cart $cart, CustomerAddress $address): array
    {
        $cacheKey = "shipping_rates_{$cart->id}_{$address->id}";
        return Cache::remember($cacheKey, 600, function () use ($cart, $address) {
            $zone = $this->resolveZoneForAddress($address);
            if ($zone === null) return [];
            $methods = ShippingMethod::where('shipping_zone_id', $zone->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            $rates = [];
            foreach ($methods as $method) {
                try {
                    $carrier = $this->carrierFactory->make($method);
                    $rateFils = $carrier->calculateRate($method, $cart);
                    $rates[] = ['method' => $method, 'rate_fils' => $rateFils];
                } catch (\Throwable) {
                    // skip unsupported/erroring carriers
                }
            }
            return $rates;
        });
    }

    public function validateShippingMethodForCart(
        Cart $cart,
        int $methodId,
        CustomerAddress $address,
    ): ShippingMethod {
        $method = ShippingMethod::with('zone')->findOrFail($methodId);
        if (! $method->is_active) {
            throw ValidationException::withMessages(['shipping_method_id' => ['Shipping method is not available.']]);
        }
        $zone = $this->resolveZoneForAddress($address);
        if ($zone === null || $method->shipping_zone_id !== $zone->id) {
            throw ValidationException::withMessages(['shipping_method_id' => ['Shipping method is not available for your address.']]);
        }
        return $method;
    }

    public function isVirtualCart(Cart $cart): bool
    {
        $cart->loadMissing('items.variant.product');
        if ($cart->items->isEmpty()) return false;
        return $cart->items->every(
            fn ($item) => ($item->variant?->product?->product_type ?? 'simple') === 'virtual'
        );
    }

    public function attachShippingToOrder(Order $order, ShippingMethod $method, int $rateFils): OrderShipping
    {
        $existing = $order->shipping()->first();
        if ($existing !== null) return $existing;
        return OrderShipping::create([
            'order_id'           => $order->id,
            'shipping_method_id' => $method->id,
            'carrier'            => $method->carrier,
            'method_name_en'     => $method->name_en,
            'method_name_ar'     => $method->name_ar,
            'rate_fils'          => $rateFils,
        ]);
    }
}
```

### `app/Modules/Orders/Services/OrderService.php` — checkout() signature
```php
public function checkout(
    Cart $cart,
    ?int $userId,
    ?string $guestEmail,
    int $shippingAddressId,
    int $billingAddressId,
    string $paymentMethod,
    ?string $notes = null,
    string $locale = 'ar',
    ?int $shippingMethodId = null,  // ← added
): Order
```

Inside checkout, BEFORE transaction:
```php
$shippingMethod = null;
$shippingRateFils = 0;
if (! $this->shippingService->isVirtualCart($cart)) {
    if ($shippingMethodId === null) {
        throw ValidationException::withMessages(['shipping_method_id' => ['Shipping method is required.']]);
    }
    $shippingMethod = $this->shippingService->validateShippingMethodForCart($cart, $shippingMethodId, $shippingAddress);
    $rates = $this->shippingService->getAvailableRates($cart, $shippingAddress);
    $found = collect($rates)->firstWhere(fn ($r) => $r['method']->id === $shippingMethod->id);
    $shippingRateFils = $found ? $found['rate_fils'] : 0;
}
```

After transaction (OUTSIDE the DB::transaction closure):
```php
if ($shippingMethod !== null) {
    $this->shippingService->attachShippingToOrder($order, $shippingMethod, $shippingRateFils);
}
return $order;
```

**BUG NOTE:** The current `OrderService.php` has dead code — the `attachShippingToOrder` call is after a `return` statement inside the transaction. This needs fixing in Task 4 when `OrderShippingResource` is added — move the shipping attachment outside the transaction block properly.

---

## Remaining Tasks

### Task 4 — API Resources (START HERE)

**Files to create:**
1. `app/Modules/Shipping/Resources/OrderShippingResource.php`
```php
<?php
declare(strict_types=1);

namespace App\Modules\Shipping\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderShippingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'carrier'        => $this->carrier,
            'method_name_en' => $this->method_name_en,
            'method_name_ar' => $this->method_name_ar,
            'rate_fils'      => $this->rate_fils,
            'tracking_number'=> $this->tracking_number,
        ];
    }
}
```

**Files to update:**
2. `app/Modules/Orders/Resources/OrderResource.php` — add to toArray():
```php
'shipping' => new OrderShippingResource($this->whenLoaded('shipping')),
```

3. Also FIX the dead code bug in `OrderService::checkout()` — the `attachShippingToOrder` and second `return $order` are after the `return DB::transaction(...)` statement, so they're unreachable. Restructure:
```php
$order = DB::transaction(function () use (...) {
    // ... existing transaction body ...
    // REMOVE the shipping from $order->load() inside the transaction
    return $order->load(['items', 'statusHistory', 'shippingAddress', 'billingAddress']);
});

// Attach shipping OUTSIDE transaction
if ($shippingMethod !== null) {
    $this->shippingService->attachShippingToOrder($order, $shippingMethod, $shippingRateFils);
}

return $order->load('shipping'); // reload with shipping
```

---

### Task 5 — Filament Admin

**Files to create:**

1. `app/Modules/Shipping/Filament/Resources/ShippingZoneResource.php`
   - Table: id, name_en, countries (badge list), is_active (badge), sort_order
   - Form (Schema API): name_en, name_ar, countries (text — comma-separated or tags), is_active (toggle), sort_order
   - Has `ShippingMethodsRelationManager`

2. `app/Modules/Shipping/Filament/Resources/ShippingZoneResource/RelationManagers/ShippingMethodsRelationManager.php`
   - Table: carrier, name_en, type, rate_fils, free_threshold_fils, is_active, sort_order
   - Form: all ShippingMethod fields

3. `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/OrderShippingRelationManager.php`
   - Read-only table showing: carrier, method_name_en, rate_fils, tracking_number

4. `app/Modules/Orders/Filament/Resources/OrderResource.php` — add OrderShippingRelationManager to getRelations()

**Files to update:**
5. `app/Providers/Filament/AdminPanelProvider.php` — add:
```php
->discoverResources(
    in: app_path('Modules/Shipping/Filament/Resources'),
    for: 'App\\Modules\\Shipping\\Filament\\Resources',
)
```

**Filament v5 reminders:**
- ALL form/table methods use `Schema $schema: Schema` from `Filament\Schemas\Schema`
- Use `$schema->schema([...])` NOT `->components([...])`
- `TextColumn::make()->badge()` NOT `BadgeColumn`
- Check existing resources (ProductResource, OrderResource) for patterns

---

### Task 6 — Seeder

**File to create:** `database/seeders/ShippingSeeder.php`

```php
// Bahrain zone with two methods
ShippingZone::firstOrCreate(['name_en' => 'Bahrain'], [
    'name_ar' => 'البحرين',
    'countries' => ['BH'],
    'is_active' => true,
    'sort_order' => 0,
]);
// Method 1: flat_rate — 1500 fils (1.500 BHD)
// Method 2: free_threshold — free above 20000 fils (20 BHD), rate 1500 fils below

// Saudi Arabia zone (stub, inactive ok)
// UAE zone (stub, inactive ok)
```

**File to update:** `database/seeders/DatabaseSeeder.php` — add `$this->call(ShippingSeeder::class);`

---

### Task 7 — Tests

**Files to create:**

1. `tests/Feature/Shipping/ShippingTest.php` (~15 tests)
   - `test_rates_returns_available_methods_for_address`
   - `test_rates_returns_empty_for_unknown_zone`
   - `test_rates_requires_address_id`
   - `test_rates_rejects_other_users_address`
   - `test_virtual_cart_returns_empty_rates` (or just bypasses)
   - `test_flat_rate_carrier_calculates_correct_rate`
   - `test_free_threshold_carrier_returns_zero_above_threshold`
   - `test_free_threshold_carrier_returns_rate_below_threshold`
   - `test_validate_shipping_method_rejects_inactive_method`
   - `test_validate_shipping_method_rejects_wrong_zone`
   - `test_resolve_zone_returns_null_for_empty_zone_table`
   - `test_is_virtual_cart_true_when_all_virtual`
   - `test_is_virtual_cart_false_when_mixed`
   - `test_rates_cached_for_600_seconds`
   - `test_unauthenticated_rates_request_rejected`

2. `tests/Feature/Shipping/CheckoutWithShippingTest.php` (~7 tests)
   - `test_checkout_with_flat_rate_adds_delivery_fee`
   - `test_checkout_requires_shipping_method_for_physical_cart`
   - `test_checkout_skips_shipping_for_virtual_cart`
   - `test_checkout_rejects_invalid_shipping_method`
   - `test_checkout_rejects_shipping_method_for_wrong_zone`
   - `test_order_resource_includes_shipping`
   - `test_checkout_total_includes_shipping_rate`

**Target:** 338 + ~22 = ~360 tests passing

---

## Order of Execution

```
Task 4 → Task 5 → Task 6 → Task 7
```

Each task: implementer subagent → spec review → quality review → commit → next task.

---

## Subagent Context to Include

When dispatching Task 4 implementer, include:
- This file path for reference
- Worktree path: `C:/Users/User/Desktop/souq/Souq/.worktrees/feat-3d1-shipping`
- PHP binary for tests
- The dead code bug in OrderService::checkout() that must be fixed
- Current OrderShippingResource.php is a placeholder at `app/Modules/Shipping/Resources/ShippingRateResource.php`
- `OrderResource.php` needs `'shipping'` field added
- MUST use `declare(strict_types=1)` at top of every PHP file
- MUST NOT use float/decimal for money — integer fils only
