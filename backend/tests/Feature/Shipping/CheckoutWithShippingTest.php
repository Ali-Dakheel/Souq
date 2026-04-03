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
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckoutWithShippingTest extends TestCase
{
    use RefreshDatabase;

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

    private function checkoutPayload(CustomerAddress $addr, array $overrides = []): array
    {
        return array_merge([
            'shipping_address_id' => $addr->id,
            'billing_address_id' => $addr->id,
            'payment_method' => 'benefit',
        ], $overrides);
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
    // Test: Checkout requires shipping method for physical cart
    // -----------------------------------------------------------------------

    public function test_checkout_requires_shipping_method_for_physical_cart(): void
    {
        Event::fake([OrderPlaced::class]);

        $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_method_id']);

        Event::assertNotDispatched(OrderPlaced::class);
    }

    // -----------------------------------------------------------------------
    // Test: Checkout skips shipping for virtual cart
    // -----------------------------------------------------------------------

    public function test_checkout_skips_shipping_for_virtual_cart(): void
    {
        Event::fake([OrderPlaced::class]);

        $user = User::factory()->create();
        $variant = $this->makeVirtualVariant();
        // Create inventory even for virtual products (checkout logic still checks inventory)
        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr));

        $response->assertStatus(201)
            ->assertJsonPath('data.order_status', 'pending');

        Event::assertDispatched(OrderPlaced::class);
    }

    // -----------------------------------------------------------------------
    // Test: Checkout with flat rate adds delivery fee
    // -----------------------------------------------------------------------

    public function test_checkout_with_flat_rate_adds_delivery_fee(): void
    {
        Event::fake([OrderPlaced::class]);

        [$zone, $flat, $free] = $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr, [
                'shipping_method_id' => $flat->id,
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.delivery_fee_fils', 1500);

        Event::assertDispatched(OrderPlaced::class);
    }

    // -----------------------------------------------------------------------
    // Test: Checkout total includes shipping rate
    // -----------------------------------------------------------------------

    public function test_checkout_total_includes_shipping_rate(): void
    {
        Event::fake([OrderPlaced::class]);

        [$zone, $flat, $free] = $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(10000, 10); // 10000 fils
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr, [
                'shipping_method_id' => $flat->id,
            ]));

        $response->assertStatus(201);

        // Verify: subtotal=10000, vat=1000 (10%), delivery_fee=1500, total=12500
        $response->assertJsonPath('data.subtotal_fils', 10000)
            ->assertJsonPath('data.vat_fils', 1000)
            ->assertJsonPath('data.delivery_fee_fils', 1500)
            ->assertJsonPath('data.total_fils', 12500);

        Event::assertDispatched(OrderPlaced::class);
    }

    // -----------------------------------------------------------------------
    // Test: Checkout rejects invalid shipping method
    // -----------------------------------------------------------------------

    public function test_checkout_rejects_invalid_shipping_method(): void
    {
        Event::fake([OrderPlaced::class]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr, [
                'shipping_method_id' => 99999,
            ]));

        // Invalid shipping_method_id returns 422 (validation error from Form Request)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_method_id']);

        Event::assertNotDispatched(OrderPlaced::class);
    }

    // -----------------------------------------------------------------------
    // Test: Checkout rejects shipping method for wrong zone
    // -----------------------------------------------------------------------

    public function test_checkout_rejects_shipping_method_for_wrong_zone(): void
    {
        Event::fake([OrderPlaced::class]);

        // Create a zone for a different country
        $wrongZone = ShippingZone::create([
            'name_en' => 'UAE',
            'name_ar' => 'الإمارات',
            'countries' => ['AE'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $wrongMethod = ShippingMethod::create([
            'shipping_zone_id' => $wrongZone->id,
            'carrier' => 'flat_rate',
            'name_en' => 'UAE Standard',
            'name_ar' => 'عادي',
            'type' => 'flat_rate',
            'rate_fils' => 1500,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr, [
                'shipping_method_id' => $wrongMethod->id,
            ]));

        $response->assertStatus(422);

        Event::assertNotDispatched(OrderPlaced::class);
    }

    // -----------------------------------------------------------------------
    // Test: Order resource includes shipping
    // -----------------------------------------------------------------------

    public function test_order_resource_includes_shipping(): void
    {
        Event::fake([OrderPlaced::class]);

        [$zone, $flat, $free] = $this->createBahrainZoneWithMethods();

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);
        $this->makeCartWithItem($user, $variant, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->checkoutPayload($addr, [
                'shipping_method_id' => $flat->id,
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.shipping.id', fn ($id) => $id > 0)
            ->assertJsonPath('data.shipping.carrier', 'flat_rate')
            ->assertJsonPath('data.shipping.method_name_en', 'Standard')
            ->assertJsonPath('data.shipping.method_name_ar', 'عادي')
            ->assertJsonPath('data.shipping.rate_fils', 1500);

        // Also verify from database
        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order->shipping);
        $this->assertEquals($flat->id, $order->shipping->shipping_method_id);

        Event::assertDispatched(OrderPlaced::class);
    }
}
