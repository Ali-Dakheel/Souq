<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\Wishlist;
use App\Modules\Customers\Services\WishlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private WishlistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WishlistService::class);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => ['ar' => 'فئة', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);
    }

    private function createProduct(): Product
    {
        return Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 5000,
        ]);
    }

    private function createVariant(?Product $product = null): Variant
    {
        if (! $product) {
            $product = $this->createProduct();
        }

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'is_available' => true,
            'attributes' => [],
        ]);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => 100,
        ]);

        return $variant;
    }

    // ========================================================================
    // UNAUTHENTICATED TESTS
    // ========================================================================

    public function test_unauthenticated_get_wishlist_returns_401(): void
    {
        $response = $this->getJson('/api/v1/wishlist');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_post_wishlist_items_returns_401(): void
    {
        $response = $this->postJson('/api/v1/wishlist/items', ['variant_id' => 1]);

        $response->assertStatus(401);
    }

    // ========================================================================
    // GET WISHLIST TESTS
    // ========================================================================

    public function test_get_wishlist_creates_on_first_access_returns_200(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/wishlist');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', fn ($id) => (int) $id > 0);
        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.is_public', false);
        $response->assertJsonPath('data.share_token', null);

        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id]);
    }

    public function test_get_wishlist_returns_items_with_variant_data(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();
        $variant = $this->createVariant($product);

        $wishlist = $this->service->getOrCreate($user);
        $this->service->addItem($wishlist, $variant->id);

        $response = $this->actingAs($user)->getJson('/api/v1/wishlist');

        $response->assertStatus(200);
        $response->assertJsonPath('data.items.0.variant_id', $variant->id);
        $response->assertJsonPath('data.items.0.variant.id', $variant->id);
        $response->assertJsonPath('data.items.0.variant.sku', $variant->sku);
        $response->assertJsonPath('data.items.0.variant.product.id', $product->id);
        $response->assertJsonPath('data.items.0.variant.product.name_en', $product->name['en']);
    }

    // ========================================================================
    // ADD ITEM TESTS
    // ========================================================================

    public function test_post_wishlist_items_adds_variant_returns_201(): void
    {
        $user = User::factory()->create();
        $variant = $this->createVariant();

        $response = $this->actingAs($user)->postJson('/api/v1/wishlist/items', [
            'variant_id' => $variant->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.items.0.variant_id', $variant->id);

        $this->assertDatabaseHas('wishlist_items', [
            'variant_id' => $variant->id,
        ]);
    }

    public function test_post_wishlist_items_with_missing_variant_id_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/wishlist/items', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['variant_id']);
    }

    public function test_post_wishlist_items_with_non_existent_variant_id_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/wishlist/items', [
            'variant_id' => 99999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['variant_id']);
    }

    public function test_post_wishlist_items_adding_same_variant_twice_returns_422(): void
    {
        $user = User::factory()->create();
        $variant = $this->createVariant();
        $wishlist = $this->service->getOrCreate($user);

        $this->service->addItem($wishlist, $variant->id);

        $response = $this->actingAs($user)->postJson('/api/v1/wishlist/items', [
            'variant_id' => $variant->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['variant_id']);
        $response->assertJsonPath('errors.variant_id.0', 'Item already in wishlist.');
    }

    // ========================================================================
    // REMOVE ITEM TESTS
    // ========================================================================

    public function test_delete_wishlist_items_removes_item_returns_200(): void
    {
        $user = User::factory()->create();
        $variant = $this->createVariant();
        $wishlist = $this->service->getOrCreate($user);
        $this->service->addItem($wishlist, $variant->id);

        $response = $this->actingAs($user)->deleteJson("/api/v1/wishlist/items/{$variant->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Removed.');

        $this->assertDatabaseMissing('wishlist_items', [
            'wishlist_id' => $wishlist->id,
            'variant_id' => $variant->id,
        ]);
    }

    public function test_delete_wishlist_items_with_non_existent_variant_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/v1/wishlist/items/99999');

        $response->assertStatus(404);
    }

    public function test_delete_wishlist_items_wrong_user_variant_returns_404(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $variant = $this->createVariant();

        $wishlist1 = $this->service->getOrCreate($user1);
        $this->service->addItem($wishlist1, $variant->id);

        // User 2 tries to remove item from user 1's wishlist
        $response = $this->actingAs($user2)->deleteJson("/api/v1/wishlist/items/{$variant->id}");

        $response->assertStatus(404);

        // Verify item still exists in user 1's wishlist
        $this->assertDatabaseHas('wishlist_items', [
            'wishlist_id' => $wishlist1->id,
            'variant_id' => $variant->id,
        ]);
    }

    // ========================================================================
    // SHARE TOKEN TESTS
    // ========================================================================

    public function test_post_wishlist_share_generates_token_sets_public_returns_200(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/wishlist/share');

        $response->assertStatus(200);
        $response->assertJsonPath('share_token', fn ($token) => strlen($token) > 0);
        $response->assertJsonPath('share_url', fn ($url) => str_contains($url, '/api/v1/wishlists/shared/'));

        $wishlist = Wishlist::where('user_id', $user->id)->first();
        $this->assertTrue($wishlist->is_public);
        $this->assertNotNull($wishlist->share_token);
    }

    public function test_post_wishlist_share_called_twice_reuses_same_token(): void
    {
        $user = User::factory()->create();

        $response1 = $this->actingAs($user)->postJson('/api/v1/wishlist/share');
        $token1 = $response1->json('share_token');

        $response2 = $this->actingAs($user)->postJson('/api/v1/wishlist/share');
        $token2 = $response2->json('share_token');

        // Both calls should update the same wishlist, not create a new token
        $this->assertEquals($token1, $token2);
        $this->assertDatabaseCount('wishlists', 1);
    }

    // ========================================================================
    // PUBLIC SHARED WISHLIST TESTS
    // ========================================================================

    public function test_get_wishlists_shared_token_returns_public_wishlist_returns_200(): void
    {
        $user = User::factory()->create();
        $variant = $this->createVariant();

        $wishlist = $this->service->getOrCreate($user);
        $this->service->addItem($wishlist, $variant->id);
        $wishlist = $this->service->generateShareToken($wishlist);

        $response = $this->getJson("/api/v1/wishlists/shared/{$wishlist->share_token}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $wishlist->id);
        $response->assertJsonPath('data.is_public', true);
        $response->assertJsonPath('data.items.0.variant_id', $variant->id);
    }

    public function test_get_wishlists_shared_with_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/api/v1/wishlists/shared/invalid-token-12345');

        $response->assertStatus(404);
    }

    public function test_get_wishlists_shared_when_is_public_false_returns_404(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->service->getOrCreate($user);

        // Wishlist is private (is_public = false)
        $response = $this->getJson("/api/v1/wishlists/shared/{$wishlist->share_token}");

        $response->assertStatus(404);
    }

    // ========================================================================
    // MOVE TO CART TESTS
    // ========================================================================

    public function test_post_wishlist_items_move_to_cart_adds_item_to_cart_returns_200(): void
    {
        $user = User::factory()->create();
        $variant = $this->createVariant();

        $wishlist = $this->service->getOrCreate($user);
        $this->service->addItem($wishlist, $variant->id);

        $response = $this->actingAs($user)->postJson("/api/v1/wishlist/items/{$variant->id}/move-to-cart");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Item moved to cart.');
        $response->assertJsonPath('cart.items.0.variant_id', $variant->id);

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
        ]);
    }

    // ========================================================================
    // CROSS-USER ISOLATION TESTS
    // ========================================================================

    public function test_wishlist_items_belong_to_correct_wishlist_owner(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $variant1 = $this->createVariant();
        $variant2 = $this->createVariant();

        $wishlist1 = $this->service->getOrCreate($user1);
        $wishlist2 = $this->service->getOrCreate($user2);

        $this->service->addItem($wishlist1, $variant1->id);
        $this->service->addItem($wishlist2, $variant2->id);

        $response1 = $this->actingAs($user1)->getJson('/api/v1/wishlist');
        $response2 = $this->actingAs($user2)->getJson('/api/v1/wishlist');

        $response1->assertJsonPath('data.items.0.variant_id', $variant1->id);
        $response1->assertJsonCount(1, 'data.items');

        $response2->assertJsonPath('data.items.0.variant_id', $variant2->id);
        $response2->assertJsonCount(1, 'data.items');
    }
}
