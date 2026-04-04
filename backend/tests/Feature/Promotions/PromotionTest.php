<?php

declare(strict_types=1);

namespace Tests\Feature\Promotions;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Promotions\Models\PromotionAction;
use App\Modules\Promotions\Models\PromotionCondition;
use App\Modules\Promotions\Models\PromotionRule;
use App\Modules\Promotions\Models\PromotionUsage;
use App\Modules\Promotions\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $promotionService;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->promotionService = app(PromotionService::class);
        $this->cartService = app(CartService::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeVariant(int $priceFils = 5000): Variant
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
            'quantity_available' => 100,
            'quantity_reserved' => 0,
        ]);

        return $variant->load(['product', 'inventory']);
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

        return $cart->load('items.variant.product.category');
    }

    // -----------------------------------------------------------------------
    // Test Group 1: getApplicableRules — Condition Types
    // -----------------------------------------------------------------------

    public function test_cart_total_gte_condition_applies_when_total_gte_threshold(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000); // 5 BHD
        $cart = $this->makeCartWithItem($user, $variant, 2); // 10000 fils total

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertTrue($applicable->contains($rule));
    }

    public function test_cart_total_gte_condition_does_not_apply_when_total_lt_threshold(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(2000); // 2 BHD
        $cart = $this->makeCartWithItem($user, $variant, 1); // 2000 fils total

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_item_qty_gte_condition_applies_when_qty_gte_threshold(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant, 4); // 4 items total

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'item_qty',
            'operator' => 'gte',
            'value' => 3,
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertTrue($applicable->contains($rule));
    }

    public function test_item_qty_gte_condition_does_not_apply_when_qty_lt_threshold(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant, 2); // 2 items total

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'item_qty',
            'operator' => 'gte',
            'value' => 3,
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_customer_group_in_condition_applies_when_user_in_group(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'VIP',
            'name_ar' => 'VIP',
            'slug' => 'vip',
        ]);

        $user = User::factory()->create(['customer_group_id' => $group->id]);
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'customer_group',
            'operator' => 'in',
            'value' => [$group->id],
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertTrue($applicable->contains($rule));
    }

    public function test_customer_group_in_condition_does_not_apply_for_guest(): void
    {
        $group = CustomerGroup::create([
            'name_en' => 'VIP',
            'name_ar' => 'VIP',
            'slug' => 'vip',
        ]);

        $user = User::factory()->create(); // No group assigned
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'customer_group',
            'operator' => 'in',
            'value' => [$group->id],
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_customer_group_in_condition_does_not_apply_for_different_group(): void
    {
        $groupVip = CustomerGroup::create([
            'name_en' => 'VIP',
            'name_ar' => 'VIP',
            'slug' => 'vip',
        ]);

        $groupStandard = CustomerGroup::create([
            'name_en' => 'Standard',
            'name_ar' => 'Standard',
            'slug' => 'standard',
        ]);

        $user = User::factory()->create(['customer_group_id' => $groupStandard->id]);
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'customer_group',
            'operator' => 'in',
            'value' => [$groupVip->id],
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_product_in_cart_in_condition_applies_when_product_in_cart(): void
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => 5000,
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
            'quantity_available' => 100,
            'quantity_reserved' => 0,
        ]);

        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'product_in_cart',
            'operator' => 'in',
            'value' => [$product->id],
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertTrue($applicable->contains($rule));
    }

    public function test_product_in_cart_in_condition_does_not_apply_when_product_not_in_cart(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $otherProduct = Product::create([
            'name' => ['ar' => 'منتج آخر', 'en' => 'Other Product'],
            'slug' => 'other-prod-'.uniqid(),
            'category_id' => $variant->product->category_id,
            'base_price_fils' => 5000,
            'is_available' => true,
            'product_type' => 'simple',
        ]);

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'product_in_cart',
            'operator' => 'in',
            'value' => [$otherProduct->id],
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_category_in_cart_in_condition_applies_when_category_in_cart(): void
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => 5000,
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
            'quantity_available' => 100,
            'quantity_reserved' => 0,
        ]);

        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Test Rule',
            'name_ar' => 'قاعدة اختبار',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'category_in_cart',
            'operator' => 'in',
            'value' => [$category->id],
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertTrue($applicable->contains($rule));
    }

    // -----------------------------------------------------------------------
    // Test Group 2: Exclusive Rules
    // -----------------------------------------------------------------------

    public function test_exclusive_rule_stops_further_rules_from_being_applied(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant, 3); // 15000 fils

        // Rule 1: priority 5, exclusive, applies at 5000+
        $rule1 = PromotionRule::create([
            'name_en' => 'Exclusive Rule',
            'name_ar' => 'قاعدة حصرية',
            'is_active' => true,
            'priority' => 5,
            'is_exclusive' => true,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule1->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        // Rule 2: priority 10, non-exclusive, applies at 10000+
        $rule2 = PromotionRule::create([
            'name_en' => 'Non-Exclusive Rule',
            'name_ar' => 'قاعدة غير حصرية',
            'is_active' => true,
            'priority' => 10,
            'is_exclusive' => false,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule2->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 10000,
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        // Only rule1 should be applicable
        $this->assertTrue($applicable->contains($rule1));
        $this->assertFalse($applicable->contains($rule2));
        $this->assertCount(1, $applicable);
    }

    public function test_non_exclusive_rule_allows_subsequent_rules_to_apply(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant, 3); // 15000 fils

        // Rule 1: priority 5, non-exclusive, applies at 5000+
        $rule1 = PromotionRule::create([
            'name_en' => 'Rule 1',
            'name_ar' => 'قاعدة 1',
            'is_active' => true,
            'priority' => 5,
            'is_exclusive' => false,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule1->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        // Rule 2: priority 10, non-exclusive, applies at 10000+
        $rule2 = PromotionRule::create([
            'name_en' => 'Rule 2',
            'name_ar' => 'قاعدة 2',
            'is_active' => true,
            'priority' => 10,
            'is_exclusive' => false,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule2->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 10000,
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        // Both rules should be applicable
        $this->assertTrue($applicable->contains($rule1));
        $this->assertTrue($applicable->contains($rule2));
        $this->assertCount(2, $applicable);
    }

    // -----------------------------------------------------------------------
    // Test Group 3: Usage Limits
    // -----------------------------------------------------------------------

    public function test_rule_is_skipped_when_global_usage_limit_reached(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Limited Rule',
            'name_ar' => 'قاعدة محدودة',
            'is_active' => true,
            'priority' => 10,
            'max_uses_global' => 1,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 1000,
        ]);

        // Create one usage record
        PromotionUsage::create([
            'promotion_rule_id' => $rule->id,
            'user_id' => $user->id,
            'used_at' => now(),
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_rule_is_skipped_when_per_user_limit_reached(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Limited Rule',
            'name_ar' => 'قاعدة محدودة',
            'is_active' => true,
            'priority' => 10,
            'max_uses_per_user' => 1,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 1000,
        ]);

        // Create one per-user usage record
        PromotionUsage::create([
            'promotion_rule_id' => $rule->id,
            'user_id' => $user->id,
            'used_at' => now(),
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertFalse($applicable->contains($rule));
    }

    public function test_rule_still_applies_when_global_limit_not_reached(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Limited Rule',
            'name_ar' => 'قاعدة محدودة',
            'is_active' => true,
            'priority' => 10,
            'max_uses_global' => 5,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 1000,
        ]);

        // Create 2 usage records (out of 5 limit)
        PromotionUsage::create([
            'promotion_rule_id' => $rule->id,
            'user_id' => $user->id,
            'used_at' => now(),
        ]);
        PromotionUsage::create([
            'promotion_rule_id' => $rule->id,
            'user_id' => User::factory()->create()->id,
            'used_at' => now(),
        ]);

        $applicable = $this->promotionService->getApplicableRules($cart, $user);

        $this->assertTrue($applicable->contains($rule));
    }

    // -----------------------------------------------------------------------
    // Test Group 4: calculateActionDiscount
    // -----------------------------------------------------------------------

    public function test_percent_off_cart_applies_percentage_to_subtotal(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(10000); // 10 BHD
        $cart = $this->makeCartWithItem($user, $variant, 1); // 10000 fils subtotal

        $rule = PromotionRule::create([
            'name_en' => 'Percent Off',
            'name_ar' => 'نسبة خصم',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 10],
        ]);

        $result = $this->promotionService->calculateActionDiscount($rule, $cart);

        // 10% of 10000 = 1000 fils
        $this->assertEquals(1000, $result['promotion_discount_fils']);
        $this->assertFalse($result['free_shipping']);
    }

    public function test_fixed_off_cart_deducts_fixed_amount(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(10000);
        $cart = $this->makeCartWithItem($user, $variant, 1); // 10000 fils subtotal

        $rule = PromotionRule::create([
            'name_en' => 'Fixed Off',
            'name_ar' => 'مبلغ ثابت',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'fixed_off_cart',
            'value' => ['amount_fils' => 2000],
        ]);

        $result = $this->promotionService->calculateActionDiscount($rule, $cart);

        $this->assertEquals(2000, $result['promotion_discount_fils']);
        $this->assertFalse($result['free_shipping']);
    }

    public function test_fixed_off_cart_does_not_go_negative(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant, 1); // 5000 fils subtotal

        $rule = PromotionRule::create([
            'name_en' => 'Fixed Off',
            'name_ar' => 'مبلغ ثابت',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'fixed_off_cart',
            'value' => ['amount_fils' => 10000], // More than subtotal
        ]);

        $result = $this->promotionService->calculateActionDiscount($rule, $cart);

        // Should be capped at subtotal
        $this->assertEquals(5000, $result['promotion_discount_fils']);
    }

    public function test_free_shipping_returns_free_shipping_flag(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $rule = PromotionRule::create([
            'name_en' => 'Free Shipping',
            'name_ar' => 'شحن مجاني',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'free_shipping',
            'value' => [],
        ]);

        $result = $this->promotionService->calculateActionDiscount($rule, $cart);

        $this->assertEquals(0, $result['promotion_discount_fils']);
        $this->assertTrue($result['free_shipping']);
    }

    public function test_bogo_gives_50_percent_off_lowest_price_item(): void
    {
        $user = User::factory()->create();

        // Add two variants with different prices
        $variant1 = $this->makeVariant(10000); // 10 BHD
        $variant2 = $this->makeVariant(5000);  // 5 BHD

        $cart = Cart::create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant1->id,
            'quantity' => 1,
            'price_fils_snapshot' => 10000,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant2->id,
            'quantity' => 1,
            'price_fils_snapshot' => 5000,
        ]);

        $cart->load('items.variant.product');

        $rule = PromotionRule::create([
            'name_en' => 'BOGO',
            'name_ar' => 'شراء واحد احصل على واحد',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'bogo',
            'value' => [],
        ]);

        $result = $this->promotionService->calculateActionDiscount($rule, $cart);

        // 50% off lowest item (5000 fils) = 2500 fils
        $this->assertEquals(2500, $result['promotion_discount_fils']);
    }

    public function test_multiple_actions_on_one_rule_stack_correctly(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(10000);
        $cart = $this->makeCartWithItem($user, $variant, 1); // 10000 fils

        $rule = PromotionRule::create([
            'name_en' => 'Multi Action',
            'name_ar' => 'عدة إجراءات',
            'is_active' => true,
            'priority' => 10,
        ]);

        // Action 1: 10% off
        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 10],
        ]);

        // Action 2: fixed 500 fils off
        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'fixed_off_cart',
            'value' => ['amount_fils' => 500],
        ]);

        $result = $this->promotionService->calculateActionDiscount($rule, $cart);

        // 10% of 10000 = 1000, plus 500 fixed = 1500 total
        $this->assertEquals(1500, $result['promotion_discount_fils']);
    }

    // -----------------------------------------------------------------------
    // Test Group 5: CartService Integration
    // -----------------------------------------------------------------------

    public function test_calculate_totals_includes_promotion_discount_fils_key(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCartWithItem($user, $variant);

        $totals = $this->cartService->calculateTotals($cart);

        $this->assertArrayHasKey('promotion_discount_fils', $totals);
    }

    public function test_promotion_discount_fils_is_zero_when_no_rules_match(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(2000); // Small cart
        $cart = $this->makeCartWithItem($user, $variant);

        // Create a rule that won't match
        $rule = PromotionRule::create([
            'name_en' => 'High Threshold',
            'name_ar' => 'حد عالي',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 100000, // Very high
        ]);

        $totals = $this->cartService->calculateTotals($cart);

        $this->assertEquals(0, $totals['promotion_discount_fils']);
    }

    public function test_promotion_discount_fils_reflects_applicable_promotion_discount(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(10000); // 10 BHD
        $cart = $this->makeCartWithItem($user, $variant, 1);

        $rule = PromotionRule::create([
            'name_en' => 'Test Promo',
            'name_ar' => 'عرض تجريبي',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 20],
        ]);

        $totals = $this->cartService->calculateTotals($cart);

        // 20% of 10000 = 2000 fils
        $this->assertEquals(2000, $totals['promotion_discount_fils']);
    }

    public function test_combined_coupon_and_promotion_discount_is_applied_correctly(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(10000); // 10 BHD
        $cart = $this->makeCartWithItem($user, $variant, 1);

        // Apply a promotion rule (10% off)
        $promoRule = PromotionRule::create([
            'name_en' => 'Test Promo',
            'name_ar' => 'عرض تجريبي',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $promoRule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $promoRule->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 10],
        ]);

        // Create a coupon for additional discount
        $coupon = Coupon::create([
            'code' => 'TESTCODE',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'minimum_order_amount_fils' => 5000,
            'is_active' => true,
            'max_uses_global' => 100,
        ]);

        $cart->update(['coupon_code' => $coupon->code]);

        $totals = $this->cartService->calculateTotals($cart);

        // Subtotal: 10000
        // Coupon (5%): 500 fils
        // Promotion (10% of remaining): min(10% * 10000, 10000 - 500) = 950 fils
        // So: coupon (500) + promo (950) = 1450, but capped at 10000
        // Expected: discount_fils=500, promotion_discount_fils=950
        $this->assertEquals(500, $totals['discount_fils']);
        $this->assertEquals(950, $totals['promotion_discount_fils']);

        // Taxable = 10000 - 500 - 950 = 8550
        // VAT = 8550 * 0.10 = 855
        // Total = 8550 + 855 = 9405
        $this->assertEquals(8550, $totals['subtotal_fils'] - $totals['discount_fils'] - $totals['promotion_discount_fils']);
    }

    // -----------------------------------------------------------------------
    // Test Group 6: API Endpoint
    // -----------------------------------------------------------------------

    public function test_get_promotions_applicable_returns_401_for_guests(): void
    {
        $response = $this->getJson('/api/v1/promotions/applicable');

        $this->assertResponseStatus($response, 401);
    }

    public function test_get_promotions_applicable_returns_empty_array_when_no_applicable_promotions(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(2000); // Small cart
        $cart = $this->makeCartWithItem($user, $variant);

        // Create a rule that won't match
        $rule = PromotionRule::create([
            'name_en' => 'High Threshold',
            'name_ar' => 'حد عالي',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 100000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/promotions/applicable');

        $this->assertResponseStatus($response, 200);
        $this->assertEquals([], $response->json('data'));
    }

    public function test_get_promotions_applicable_returns_applicable_rules_with_actions(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant(10000); // 10 BHD
        $cart = $this->makeCartWithItem($user, $variant, 1);

        $rule = PromotionRule::create([
            'name_en' => 'Test Promo',
            'name_ar' => 'عرض تجريبي',
            'is_active' => true,
            'priority' => 10,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 20],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/promotions/applicable');

        $this->assertResponseStatus($response, 200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($rule->id, $response->json('data.0.id'));
        $this->assertEquals('Test Promo', $response->json('data.0.name_en'));
        $this->assertIsArray($response->json('data.0.actions'));
    }

    private function assertResponseStatus($response, $status): void
    {
        $this->assertEquals($status, $response->getStatusCode());
    }
}
