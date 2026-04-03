<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Shipping\Carriers\FlatRateCarrier;
use App\Modules\Shipping\Carriers\FreeThresholdCarrier;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    private ShippingService $shippingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shippingService = app(ShippingService::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeVariantWithStock(int $priceFils = 5000, int $stock = 10): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => $priceFils,
            'is_available' => true,
            'product_type' => 'simple',
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'attributes' => [],
            'is_available' => true,
        ]);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => $stock,
            'quantity_reserved' => 0,
        ]);

        return $variant->load(['product', 'inventory']);
    }

    private function makeVirtualVariant(): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج افتراضي', 'en' => 'Virtual Product'],
            'slug' => 'virtual-prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => 5000,
            'is_available' => true,
            'product_type' => 'virtual',
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'VSKU-'.uniqid(),
            'attributes' => [],
            'is_available' => true,
        ]);

        return $variant->load(['product']);
    }

    private function makeAddress(User $user): CustomerAddress
    {
        return CustomerAddress::create([
            'user_id' => $user->id,
            'address_type' => 'shipping',
            'recipient_name' => 'Ali Test',
            'phone' => '+97366000000',
            'governorate' => 'Capital',
            'district' => 'Manama',
            'street_address' => '123 Test St',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function makeCartWithItem(User $user, Variant $variant, int $qty = 1): Cart
    {
        $cart = Cart::create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => $qty,
            'price_fils_snapshot' => $variant->product->base_price_fils,
        ]);

        return $cart->load('items.variant.product.category', 'items.variant.inventory');
    }

    private function createBahrainZoneWithMethods(): array
    {
        $zone = ShippingZone::create([
            'name_en' => 'Bahrain',
            'name_ar' => 'البحرين',
            'countries' => ['BH'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $flat = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'carrier' => 'flat_rate',
            'name_en' => 'Standard',
            'name_ar' => 'عادي',
            'type' => 'flat_rate',
            'rate_fils' => 1500,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $free = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'carrier' => 'standard',
            'name_en' => 'Free above 20',
            'name_ar' => 'مجاني',
            'type' => 'free_threshold',
            'rate_fils' => 1500,
            'free_threshold_fils' => 20000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$zone, $flat, $free];
    }

    // -----------------------------------------------------------------------
    // Test: Unauthenticated rates request
    // -----------------------------------------------------------------------

    public function test_unauthenticated_rates_request_rejected(): void
    {
        $user = User::factory()->create();
        $addr = $this->makeAddress($user);

        $response = $this->getJson("/api/v1/shipping/rates?address_id={$addr->id}");

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Test: Rates requires address_id
    // -----------------------------------------------------------------------

    public function test_rates_requires_address_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipping/rates');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address_id']);
    }

    // -----------------------------------------------------------------------
    // Test: Rejects other user's address
    // -----------------------------------------------------------------------

    public function test_rates_rejects_other_users_address(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $addr = $this->makeAddress($user2);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/shipping/rates?address_id={$addr->id}");

        $response->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // Test: Returns available methods for address
    // -----------------------------------------------------------------------

    public function test_rates_returns_available_methods_for_address(): void
    {
        [$zone, $flat, $free] = $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shipping/rates?address_id={$addr->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data', fn ($data) => count($data) === 2)
            ->assertJsonPath('data.0.id', $flat->id)
            ->assertJsonPath('data.0.name_en', 'Standard')
            ->assertJsonPath('data.0.name_ar', 'عادي')
            ->assertJsonPath('data.0.carrier', 'flat_rate')
            ->assertJsonPath('data.0.type', 'flat_rate')
            ->assertJsonPath('data.0.rate_fils', 1500)
            ->assertJsonPath('data.1.id', $free->id)
            ->assertJsonPath('data.1.name_en', 'Free above 20')
            ->assertJsonPath('data.1.type', 'free_threshold')
            ->assertJsonPath('data.1.rate_fils', 1500);
    }

    // -----------------------------------------------------------------------
    // Test: Returns empty for unknown zone
    // -----------------------------------------------------------------------

    public function test_rates_returns_empty_for_unknown_zone(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        // No zones created
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shipping/rates?address_id={$addr->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    // -----------------------------------------------------------------------
    // Test: Virtual cart returns empty rates
    // -----------------------------------------------------------------------

    public function test_virtual_cart_returns_empty_rates(): void
    {
        $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVirtualVariant();
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shipping/rates?address_id={$addr->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    // -----------------------------------------------------------------------
    // Test: FlatRateCarrier calculates correct rate
    // -----------------------------------------------------------------------

    public function test_flat_rate_carrier_calculates_correct_rate(): void
    {
        $carrier = new FlatRateCarrier;

        $zone = ShippingZone::create([
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'countries' => ['BH'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'carrier' => 'flat_rate',
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'type' => 'flat_rate',
            'rate_fils' => 2500,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rate = $carrier->calculateRate($method, $cart);

        $this->assertEquals(2500, $rate);
    }

    // -----------------------------------------------------------------------
    // Test: FreeThresholdCarrier returns zero above threshold
    // -----------------------------------------------------------------------

    public function test_free_threshold_carrier_returns_zero_above_threshold(): void
    {
        $carrier = new FreeThresholdCarrier;

        $zone = ShippingZone::create([
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'countries' => ['BH'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'carrier' => 'standard',
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'type' => 'free_threshold',
            'rate_fils' => 1500,
            'free_threshold_fils' => 20000,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(25000, 10); // price 25000 > threshold 20000
        $cart = $this->makeCartWithItem($user, $variant, 1);

        $rate = $carrier->calculateRate($method, $cart);

        $this->assertEquals(0, $rate);
    }

    // -----------------------------------------------------------------------
    // Test: FreeThresholdCarrier returns rate below threshold
    // -----------------------------------------------------------------------

    public function test_free_threshold_carrier_returns_rate_below_threshold(): void
    {
        $carrier = new FreeThresholdCarrier;

        $zone = ShippingZone::create([
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'countries' => ['BH'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'carrier' => 'standard',
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'type' => 'free_threshold',
            'rate_fils' => 1500,
            'free_threshold_fils' => 20000,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(10000, 10); // price 10000 < threshold 20000
        $cart = $this->makeCartWithItem($user, $variant, 1);

        $rate = $carrier->calculateRate($method, $cart);

        $this->assertEquals(1500, $rate);
    }

    // -----------------------------------------------------------------------
    // Test: Validate shipping method rejects inactive method
    // -----------------------------------------------------------------------

    public function test_validate_shipping_method_rejects_inactive_method(): void
    {
        $zone = ShippingZone::create([
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'countries' => ['BH'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'carrier' => 'flat_rate',
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'type' => 'flat_rate',
            'rate_fils' => 1500,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000);
        $addr = $this->makeAddress($user);
        $cart = $this->makeCartWithItem($user, $variant);

        $this->expectException(\InvalidArgumentException::class);

        $this->shippingService->validateShippingMethodForCart($cart, $method->id, $addr);
    }

    // -----------------------------------------------------------------------
    // Test: Validate shipping method rejects wrong zone
    // -----------------------------------------------------------------------

    public function test_validate_shipping_method_rejects_wrong_zone(): void
    {
        $zone1 = ShippingZone::create([
            'name_en' => 'Zone 1',
            'name_ar' => 'منطقة 1',
            'countries' => ['AE'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $zone1->id,
            'carrier' => 'flat_rate',
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'type' => 'flat_rate',
            'rate_fils' => 1500,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000);
        $addr = $this->makeAddress($user);
        $cart = $this->makeCartWithItem($user, $variant);

        $this->expectException(\InvalidArgumentException::class);

        $this->shippingService->validateShippingMethodForCart($cart, $method->id, $addr);
    }

    // -----------------------------------------------------------------------
    // Test: Resolve zone returns null for empty table
    // -----------------------------------------------------------------------

    public function test_resolve_zone_returns_null_for_empty_zone_table(): void
    {
        $user = User::factory()->create();
        $addr = $this->makeAddress($user);

        $zone = $this->shippingService->resolveZoneForAddress($addr);

        $this->assertNull($zone);
    }

    // -----------------------------------------------------------------------
    // Test: isVirtualCart true when all virtual
    // -----------------------------------------------------------------------

    public function test_is_virtual_cart_true_when_all_virtual(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVirtualVariant();
        $cart = $this->makeCartWithItem($user, $variant, 2);

        $isVirtual = $this->shippingService->isVirtualCart($cart);

        $this->assertTrue($isVirtual);
    }

    // -----------------------------------------------------------------------
    // Test: isVirtualCart false when mixed
    // -----------------------------------------------------------------------

    public function test_is_virtual_cart_false_when_mixed(): void
    {
        $user = User::factory()->create();
        $physicalVariant = $this->makeVariantWithStock(5000, 10);
        $virtualVariant = $this->makeVirtualVariant();

        $cart = Cart::create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $physicalVariant->id,
            'quantity' => 1,
            'price_fils_snapshot' => $physicalVariant->product->base_price_fils,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $virtualVariant->id,
            'quantity' => 1,
            'price_fils_snapshot' => $virtualVariant->product->base_price_fils,
        ]);

        $cart = $cart->load('items.variant.product.category', 'items.variant.inventory');

        $isVirtual = $this->shippingService->isVirtualCart($cart);

        $this->assertFalse($isVirtual);
    }

    // -----------------------------------------------------------------------
    // Test: Rates cached for 600 seconds
    // -----------------------------------------------------------------------

    public function test_rates_cached_for_600_seconds(): void
    {
        $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $cart = $this->makeCartWithItem($user, $variant);

        Cache::spy();

        // First call
        $rates1 = $this->shippingService->getAvailableRates($cart, $addr);
        $this->assertCount(2, $rates1);

        // Second call should use cache
        $rates2 = $this->shippingService->getAvailableRates($cart, $addr);
        $this->assertCount(2, $rates2);

        // Verify Cache::remember was called with correct TTL
        Cache::shouldHaveReceived('remember')
            ->withArgs(function ($key, $ttl) {
                return $ttl === 600;
            })->twice();
    }
}
