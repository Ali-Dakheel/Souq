<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Customers\Models\VariantGroupPrice;
use App\Modules\Customers\Services\CustomerGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGroupTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroupService $service;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerGroupService::class);
        $this->cartService = app(CartService::class);
    }

    // ========================================================================
    // CRUD TESTS
    // ========================================================================

    public function test_list_groups_returns_collection(): void
    {
        $group1 = CustomerGroup::create([
            'name_en' => 'Premium',
            'name_ar' => 'متميز',
            'slug' => 'premium',
            'is_default' => false,
        ]);
        $group2 = CustomerGroup::create([
            'name_en' => 'Standard',
            'name_ar' => 'معياري',
            'slug' => 'standard',
            'is_default' => false,
        ]);

        $response = $this->getJson('api/v1/groups');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['id' => $group1->id, 'name_en' => 'Premium']);
        $response->assertJsonFragment(['id' => $group2->id, 'name_en' => 'Standard']);
    }

    public function test_create_group_returns_201(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('api/v1/groups', [
                'name_en' => 'VIP',
                'name_ar' => 'إضافة مميزة',
                'slug' => 'vip',
                'description' => 'VIP customers',
                'is_default' => false,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.id', fn ($id) => (int) $id > 0);
        $response->assertJsonPath('data.name_en', 'VIP');
        $response->assertJsonPath('data.name_ar', 'إضافة مميزة');
        $response->assertJsonPath('data.slug', 'vip');
        $response->assertJsonPath('data.description', 'VIP customers');
        $response->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('customer_groups', [
            'name_en' => 'VIP',
            'slug' => 'vip',
        ]);
    }

    public function test_create_group_with_is_default_true_sets_others_to_false(): void
    {
        // Create group A (default)
        $groupA = CustomerGroup::create([
            'name_en' => 'Default A',
            'name_ar' => 'الافتراضي أ',
            'slug' => 'default-a',
            'is_default' => true,
        ]);

        $this->assertTrue($groupA->fresh()->is_default);

        $user = User::factory()->create();

        // Create group B and set it as default
        $response = $this->actingAs($user)
            ->postJson('api/v1/groups', [
                'name_en' => 'Default B',
                'name_ar' => 'الافتراضي ب',
                'slug' => 'default-b',
                'is_default' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_default', true);

        // Verify A is no longer default
        $this->assertFalse($groupA->fresh()->is_default);
        $this->assertTrue($response['data']['is_default']);
    }

    public function test_create_group_without_slug_auto_generates_slug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('api/v1/groups', [
                'name_en' => 'Platinum Member',
                'name_ar' => 'عضو بلاتيني',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'platinum-member');

        $this->assertDatabaseHas('customer_groups', [
            'name_en' => 'Platinum Member',
            'slug' => 'platinum-member',
        ]);
    }

    public function test_update_group(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Old Name',
            'name_ar' => 'الاسم القديم',
            'slug' => 'old-name',
            'is_default' => false,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("api/v1/groups/{$group->id}", [
                'name_en' => 'New Name',
                'name_ar' => 'الاسم الجديد',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name_en', 'New Name');
        $response->assertJsonPath('data.name_ar', 'الاسم الجديد');

        $this->assertDatabaseHas('customer_groups', [
            'id' => $group->id,
            'name_en' => 'New Name',
        ]);
    }

    public function test_delete_non_default_group_returns_204(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Deletable',
            'name_ar' => 'قابل للحذف',
            'slug' => 'deletable',
            'is_default' => false,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("api/v1/groups/{$group->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);
    }

    public function test_delete_default_group_returns_422(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Default',
            'name_ar' => 'افتراضي',
            'slug' => 'default',
            'is_default' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("api/v1/groups/{$group->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('group');

        $this->assertDatabaseHas('customer_groups', ['id' => $group->id]);
    }

    public function test_show_group_returns_correct_fields(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Premium',
            'name_ar' => 'متميز',
            'slug' => 'premium',
            'description' => 'For premium customers',
            'is_default' => false,
        ]);

        $response = $this->getJson("api/v1/groups/{$group->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $group->id);
        $response->assertJsonPath('data.name_en', 'Premium');
        $response->assertJsonPath('data.name_ar', 'متميز');
        $response->assertJsonPath('data.slug', 'premium');
        $response->assertJsonPath('data.description', 'For premium customers');
        $response->assertJsonPath('data.is_default', false);
        // Verify created_at is present by checking it's not null
        $this->assertNotNull($response['data']['created_at']);
    }

    public function test_unauthenticated_create_returns_401(): void
    {
        $response = $this->postJson('api/v1/groups', [
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Test',
            'name_ar' => 'اختبار',
            'slug' => 'test',
            'is_default' => false,
        ]);

        $response = $this->deleteJson("api/v1/groups/{$group->id}");

        $response->assertStatus(401);
    }

    // ========================================================================
    // PRICING TESTS
    // ========================================================================

    public function test_set_group_price_returns_201(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("api/v1/groups/{$group->id}/prices", [
                'variant_id' => $variant->id,
                'price_fils' => 8000,
                'compare_at_price_fils' => 10000,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.variant_id', $variant->id);
        $response->assertJsonPath('data.customer_group_id', $group->id);
        $response->assertJsonPath('data.price_fils', 8000);
        $response->assertJsonPath('data.compare_at_price_fils', 10000);

        $this->assertDatabaseHas('variant_group_prices', [
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
            'price_fils' => 8000,
            'compare_at_price_fils' => 10000,
        ]);
    }

    public function test_update_group_price_upserts(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);
        $user = User::factory()->create();

        // Set initial price
        $this->actingAs($user)
            ->postJson("api/v1/groups/{$group->id}/prices", [
                'variant_id' => $variant->id,
                'price_fils' => 8000,
            ]);

        // Verify one row exists
        $this->assertDatabaseCount('variant_group_prices', 1);

        // Update the price
        $response = $this->actingAs($user)
            ->postJson("api/v1/groups/{$group->id}/prices", [
                'variant_id' => $variant->id,
                'price_fils' => 7500,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.price_fils', 7500);

        // Verify still only one row (upsert, not duplicate)
        $this->assertDatabaseCount('variant_group_prices', 1);
        $this->assertDatabaseHas('variant_group_prices', [
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
            'price_fils' => 7500,
        ]);
    }

    public function test_remove_group_price_returns_204(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);

        VariantGroupPrice::create([
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
            'price_fils' => 8000,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("api/v1/groups/{$group->id}/prices/{$variant->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('variant_group_prices', [
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
        ]);
    }

    public function test_get_group_price_for_user_returns_group_price(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);

        $user = User::factory()->create([
            'customer_group_id' => $group->id,
        ]);

        // Set group price
        VariantGroupPrice::create([
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
            'price_fils' => 8000,
        ]);

        $price = $this->service->getGroupPriceForUser($user, $variant);

        $this->assertEquals(8000, $price);
    }

    public function test_get_group_price_for_guest_returns_standard_price(): void
    {
        $variant = $this->createVariant(10000);

        $price = $this->service->getGroupPriceForUser(null, $variant);

        $this->assertEquals(10000, $price);
    }

    public function test_get_group_price_no_group_price_returns_standard(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);

        $user = User::factory()->create([
            'customer_group_id' => $group->id,
        ]);

        // No group price set, should fall back to variant effective price
        $price = $this->service->getGroupPriceForUser($user, $variant);

        $this->assertEquals(10000, $price);
    }

    // ========================================================================
    // CART INTEGRATION TESTS
    // ========================================================================

    public function test_cart_item_uses_group_price_for_user_with_group_price(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);
        $this->createInventory($variant, 10);

        // Set group price
        VariantGroupPrice::create([
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
            'price_fils' => 8000,
        ]);

        $user = User::factory()->create([
            'customer_group_id' => $group->id,
        ]);

        // Use actingAs to set Auth context so Auth::user() works inside CartService
        $this->actingAs($user, 'sanctum');

        $cart = $this->cartService->getOrCreateCart($user->id, null);
        $cartItem = $this->cartService->addItem($cart, $variant->id, 1);

        // Verify price snapshot captured group price
        $this->assertEquals(8000, $cartItem->price_fils_snapshot);
    }

    public function test_cart_item_uses_standard_price_for_guest(): void
    {
        $variant = $this->createVariant(10000);
        $this->createInventory($variant, 10);

        $sessionId = 'guest-'.uniqid();

        // For guest, Auth::user() returns null, so don't use actingAs
        $cart = $this->cartService->getOrCreateCart(null, $sessionId);
        $cartItem = $this->cartService->addItem($cart, $variant->id, 1);

        // Guest should use standard price
        $this->assertEquals(10000, $cartItem->price_fils_snapshot);
    }

    public function test_cart_item_uses_standard_price_for_user_without_group_price(): void
    {
        $variant = $this->createVariant(10000);
        $this->createInventory($variant, 10);

        $user = User::factory()->create(); // no customer_group_id

        // Use actingAs to set Auth context
        $this->actingAs($user, 'sanctum');

        $cart = $this->cartService->getOrCreateCart($user->id, null);
        $cartItem = $this->cartService->addItem($cart, $variant->id, 1);

        // User without group should use standard price
        $this->assertEquals(10000, $cartItem->price_fils_snapshot);
    }

    public function test_cart_total_reflects_group_price(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'Wholesale',
            'name_ar' => 'الجملة',
            'slug' => 'wholesale',
            'is_default' => false,
        ]);

        $variant = $this->createVariant(10000);
        $this->createInventory($variant, 10);

        // Set group price
        VariantGroupPrice::create([
            'variant_id' => $variant->id,
            'customer_group_id' => $group->id,
            'price_fils' => 8000,
        ]);

        $user = User::factory()->create([
            'customer_group_id' => $group->id,
        ]);

        // Use actingAs to set Auth context
        $this->actingAs($user, 'sanctum');

        $cart = $this->cartService->getOrCreateCart($user->id, null);

        // Add 2 items at group price (8000 fils each)
        $this->cartService->addItem($cart, $variant->id, 2);

        $totals = $this->cartService->calculateTotals($cart);

        // Subtotal: 2 items × 8000 = 16000 fils
        $this->assertEquals(16000, $totals['subtotal_fils']);

        // VAT: 16000 × 0.10 = 1600 fils
        $this->assertEquals(1600, $totals['vat_fils']);

        // Total: 16000 + 1600 = 17600 fils
        $this->assertEquals(17600, $totals['total_fils']);
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function createVariant(int $priceFils = 10000): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'ق', 'en' => 'C'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'م', 'en' => 'P'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => $priceFils,
            'is_available' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price_fils' => $priceFils,
            'is_available' => true,
            'attributes' => [],
        ]);

        return $variant->load(['product']);
    }

    private function createInventory(Variant $variant, int $quantity): InventoryItem
    {
        return InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => $quantity,
            'quantity_reserved' => 0,
        ]);
    }
}
