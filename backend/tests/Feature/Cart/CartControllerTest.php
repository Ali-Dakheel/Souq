<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\User;
use App\Modules\Cart\Events\CartItemAdded;
use App\Modules\Cart\Events\CartItemRemoved;
use App\Modules\Cart\Events\CouponApplied;
use App\Modules\Cart\Events\CouponRemoved;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CartControllerTest extends TestCase
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
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'is_available' => true,
            'attributes' => [],
        ]);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => $stock,
            'quantity_reserved' => 0,
        ]);

        return $variant->load(['product', 'inventory']);
    }

    private function guestHeaders(string $sessionId = 'test-session-abc'): array
    {
        return ['X-Cart-Session' => $sessionId];
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/cart
    // -----------------------------------------------------------------------

    public function test_guest_can_get_empty_cart(): void
    {
        $response = $this->getJson('/api/v1/cart', $this->guestHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('data.item_count', 0)
            ->assertJsonPath('data.subtotal_fils', 0)
            ->assertJsonPath('data.total_fils', 0);
    }

    public function test_authenticated_user_gets_their_cart(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/cart');

        $response->assertStatus(200)
            ->assertJsonPath('data.item_count', 0);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/cart/add-item
    // -----------------------------------------------------------------------

    public function test_guest_can_add_item_to_cart(): void
    {
        Event::fake([CartItemAdded::class]);

        $variant = $this->makeVariantWithStock(5000, 10);

        $response = $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ], $this->guestHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Item added to cart.')
            ->assertJsonPath('cart.item_count', 2)
            ->assertJsonPath('cart.subtotal_fils', 10000);

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price_fils_snapshot' => 5000,
        ]);

        Event::assertDispatched(CartItemAdded::class);
    }

    public function test_adding_same_variant_twice_merges_quantities(): void
    {
        $variant = $this->makeVariantWithStock(3000, 10);
        $sessionId = 'session-merge-test';

        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ], $this->guestHeaders($sessionId));

        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ], $this->guestHeaders($sessionId));

        $cart = Cart::where('session_id', $sessionId)->first();
        $item = CartItem::where('cart_id', $cart->id)->where('variant_id', $variant->id)->first();

        $this->assertEquals(5, $item->quantity);
    }

    public function test_add_item_returns_422_when_stock_exceeded(): void
    {
        $variant = $this->makeVariantWithStock(5000, 3);

        $response = $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 5,
        ], $this->guestHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_add_item_returns_422_when_variant_unavailable(): void
    {
        $variant = $this->makeVariantWithStock(5000, 10);
        $variant->update(['is_available' => false]);

        $response = $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ], $this->guestHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);
    }

    public function test_add_item_returns_422_when_out_of_stock(): void
    {
        $variant = $this->makeVariantWithStock(5000, 0);

        $response = $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ], $this->guestHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    // -----------------------------------------------------------------------
    // PUT /api/v1/cart/items/{cartItem}
    // -----------------------------------------------------------------------

    public function test_authenticated_user_can_update_cart_item(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price_fils_snapshot' => 5000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/cart/items/{$item->id}", ['quantity' => 4]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 4)
            ->assertJsonPath('cart.subtotal_fils', 20000);
    }

    public function test_user_cannot_update_another_users_cart_item(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);

        $cart1 = Cart::firstOrCreate(['user_id' => $user1->id]);
        $item = CartItem::create([
            'cart_id' => $cart1->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price_fils_snapshot' => 5000,
        ]);

        $response = $this->actingAs($user2, 'sanctum')
            ->putJson("/api/v1/cart/items/{$item->id}", ['quantity' => 3]);

        $response->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // DELETE /api/v1/cart/items/{cartItem}
    // -----------------------------------------------------------------------

    public function test_user_can_remove_cart_item(): void
    {
        Event::fake([CartItemRemoved::class]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price_fils_snapshot' => 5000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/cart/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Item removed from cart.');

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        Event::assertDispatched(CartItemRemoved::class);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/cart/apply-coupon
    // -----------------------------------------------------------------------

    public function test_guest_can_apply_valid_coupon(): void
    {
        Event::fake([CouponApplied::class]);

        $variant = $this->makeVariantWithStock(10000, 10);
        $sessionId = 'coupon-session';

        // Seed cart with item
        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ], $this->guestHeaders($sessionId));

        Coupon::create([
            'code' => 'SAVE10',
            'name' => ['ar' => 'خصم', 'en' => 'Discount'],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order_amount_fils' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'is_active' => true,
            'applicable_to' => 'all_products',
        ]);

        $response = $this->postJson('/api/v1/cart/apply-coupon', [
            'coupon_code' => 'SAVE10',
        ], $this->guestHeaders($sessionId));

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Coupon applied.')
            ->assertJsonPath('data.discount_fils', 2000); // 10% of 20000

        Event::assertDispatched(CouponApplied::class);
    }

    public function test_applying_invalid_coupon_returns_422(): void
    {
        $response = $this->postJson('/api/v1/cart/apply-coupon', [
            'coupon_code' => 'INVALID',
        ], $this->guestHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/cart/remove-coupon
    // -----------------------------------------------------------------------

    public function test_user_can_remove_coupon(): void
    {
        Event::fake([CouponRemoved::class]);

        $user = User::factory()->create();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->update(['coupon_code' => 'SAVE10']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/remove-coupon');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Coupon removed.');

        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'coupon_code' => null]);
        Event::assertDispatched(CouponRemoved::class);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/cart/clear
    // -----------------------------------------------------------------------

    public function test_user_can_clear_cart(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => 3,
            'price_fils_snapshot' => 5000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/clear');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cart cleared.');

        $this->assertEquals(0, CartItem::where('cart_id', $cart->id)->count());
    }

    // -----------------------------------------------------------------------
    // VAT calculation
    // -----------------------------------------------------------------------

    public function test_cart_totals_include_correct_vat(): void
    {
        // price: 10000 fils × 2 = subtotal 20000, VAT 10% = 2000, total = 22000
        $variant = $this->makeVariantWithStock(10000, 10);
        $sessionId = 'vat-session';

        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ], $this->guestHeaders($sessionId));

        $response = $this->getJson('/api/v1/cart', $this->guestHeaders($sessionId));

        $response->assertStatus(200)
            ->assertJsonPath('data.subtotal_fils', 20000)
            ->assertJsonPath('data.vat_fils', 2000)
            ->assertJsonPath('data.total_fils', 22000);
    }
}
