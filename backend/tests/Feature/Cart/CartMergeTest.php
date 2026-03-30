<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\User;
use App\Modules\Cart\Events\CartMerged;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariantWithStock(int $priceFils = 5000, int $stock = 10): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-' . uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-' . uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => $priceFils,
            'is_available' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-' . uniqid(),
            'is_available' => true,
        ]);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => $stock,
            'quantity_reserved' => 0,
        ]);

        return $variant;
    }

    public function test_authenticated_user_can_merge_guest_cart(): void
    {
        Event::fake([CartMerged::class]);

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $sessionId = 'merge-session-001';

        // Add item as guest
        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ], ['X-Cart-Session' => $sessionId]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [
                'guest_session_id' => $sessionId,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cart merged successfully.')
            ->assertJsonPath('items_added', 1);

        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($userCart);
        $this->assertEquals(1, CartItem::where('cart_id', $userCart->id)->count());

        // Guest cart should be deleted
        $this->assertDatabaseMissing('carts', ['session_id' => $sessionId]);

        Event::assertDispatched(CartMerged::class);
    }

    public function test_merge_sums_quantities_when_item_exists_in_both_carts(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $sessionId = 'merge-overlap-session';

        // Guest adds 2
        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ], ['X-Cart-Session' => $sessionId]);

        // User already has 3 in their cart
        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $userCart->id,
            'variant_id' => $variant->id,
            'quantity' => 3,
            'price_fils_snapshot' => 5000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [
                'guest_session_id' => $sessionId,
            ]);

        $item = CartItem::where('cart_id', $userCart->id)->where('variant_id', $variant->id)->first();
        // 3 + 2 = 5, within stock(10) and maxQty(10)
        $this->assertEquals(5, $item->quantity);
    }

    public function test_merge_caps_quantity_at_stock(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 5); // only 5 in stock
        $sessionId = 'merge-cap-session';

        // Guest has 4
        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 4,
        ], ['X-Cart-Session' => $sessionId]);

        // User already has 4 → merged would be 8, but stock=5 → should cap at 5
        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $userCart->id,
            'variant_id' => $variant->id,
            'quantity' => 4,
            'price_fils_snapshot' => 5000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [
                'guest_session_id' => $sessionId,
            ]);

        $item = CartItem::where('cart_id', $userCart->id)->where('variant_id', $variant->id)->first();
        $this->assertEquals(5, $item->quantity); // capped at stock
    }

    public function test_merge_carries_over_guest_coupon_when_user_has_none(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $sessionId = 'merge-coupon-session';

        // Add item as guest
        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ], ['X-Cart-Session' => $sessionId]);

        // Set coupon on guest cart directly
        $guestCart = Cart::where('session_id', $sessionId)->first();
        $guestCart->update(['coupon_code' => 'GUESTCODE']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [
                'guest_session_id' => $sessionId,
            ]);

        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertEquals('GUESTCODE', $userCart->coupon_code);
    }

    public function test_merge_with_empty_guest_cart_returns_gracefully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge', [
                'guest_session_id' => 'nonexistent-session',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'No guest cart found to merge.')
            ->assertJsonPath('items_added', 0);
    }

    public function test_login_with_guest_session_triggers_cart_merge(): void
    {
        $user = User::factory()->create([
            'email' => 'merge@example.com',
            'password' => bcrypt('password123'),
        ]);

        $variant = $this->makeVariantWithStock(5000, 10);
        $sessionId = 'login-merge-session';

        // Add item as guest
        $this->postJson('/api/v1/cart/add-item', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ], ['X-Cart-Session' => $sessionId]);

        // Login with guest_session_id
        $this->postJson('/api/v1/auth/login', [
            'email' => 'merge@example.com',
            'password' => 'password123',
            'guest_session_id' => $sessionId,
        ]);

        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($userCart);

        $item = CartItem::where('cart_id', $userCart->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(2, $item->quantity);

        // Guest cart should be gone
        $this->assertDatabaseMissing('carts', ['session_id' => $sessionId]);
    }
}
