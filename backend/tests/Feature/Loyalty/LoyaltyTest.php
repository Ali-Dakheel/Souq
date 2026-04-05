<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Loyalty\Jobs\EarnPointsJob;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyConfig;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\PaymentCaptured;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyService $loyaltyService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loyaltyService = app(LoyaltyService::class);
        $this->user = User::factory()->create();

        // Seed default config
        LoyaltyConfig::insert([
            ['key' => 'points_per_fil', 'value' => '1'],
            ['key' => 'fils_per_point', 'value' => '10'],
            ['key' => 'max_redeem_percent', 'value' => '0.20'],
            ['key' => 'points_expiry_days', 'value' => '365'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Account management
    // -----------------------------------------------------------------------

    public function test_creates_account_for_new_user(): void
    {
        $account = $this->loyaltyService->getOrCreateAccount($this->user);

        $this->assertInstanceOf(LoyaltyAccount::class, $account);
        $this->assertEquals($this->user->id, $account->user_id);
        $this->assertEquals(0, $account->points_balance);
        $this->assertEquals(0, $account->lifetime_points_earned);
    }

    public function test_returns_existing_account(): void
    {
        $account1 = $this->loyaltyService->getOrCreateAccount($this->user);
        $account2 = $this->loyaltyService->getOrCreateAccount($this->user);

        $this->assertEquals($account1->id, $account2->id);
        $this->assertDatabaseCount('loyalty_accounts', 1);
    }

    // -----------------------------------------------------------------------
    // earnPoints
    // -----------------------------------------------------------------------

    public function test_earns_points_from_order_subtotal(): void
    {
        // points_per_fil = 1, so 5000 fils → 5000 points
        $this->loyaltyService->earnPoints($this->user, 5000, 'order', 1);

        $account = LoyaltyAccount::where('user_id', $this->user->id)->first();
        $this->assertEquals(5000, $account->points_balance);
        $this->assertEquals(5000, $account->lifetime_points_earned);
    }

    public function test_writes_earn_transaction(): void
    {
        $this->loyaltyService->earnPoints($this->user, 10000, 'order', 42);

        $tx = LoyaltyTransaction::first();
        $this->assertEquals('earn', $tx->type);
        $this->assertEquals(10000, $tx->points);
        $this->assertEquals('order', $tx->reference_type);
        $this->assertEquals(42, $tx->reference_id);
    }

    public function test_accumulates_points_across_multiple_orders(): void
    {
        $this->loyaltyService->earnPoints($this->user, 5000, 'order', 1);
        $this->loyaltyService->earnPoints($this->user, 3000, 'order', 2);

        $account = LoyaltyAccount::where('user_id', $this->user->id)->first();
        $this->assertEquals(8000, $account->points_balance);
        $this->assertEquals(8000, $account->lifetime_points_earned);
    }

    // -----------------------------------------------------------------------
    // redeemPoints
    // -----------------------------------------------------------------------

    public function test_redeems_points_and_returns_fils_value(): void
    {
        $this->loyaltyService->earnPoints($this->user, 1000, 'order', 1);

        // Redeem 500 points at fils_per_point=10 → 5000 fils
        $filsValue = $this->loyaltyService->redeemPoints($this->user, 500, orderId: 99, orderTotalFils: 50000);

        $this->assertEquals(5000, $filsValue);
        $account = LoyaltyAccount::where('user_id', $this->user->id)->first();
        $this->assertEquals(500, $account->points_balance);
    }

    public function test_writes_redeem_transaction_with_negative_points(): void
    {
        $this->loyaltyService->earnPoints($this->user, 1000, 'order', 1);
        $this->loyaltyService->redeemPoints($this->user, 500, orderId: 99, orderTotalFils: 50000);

        $tx = LoyaltyTransaction::where('type', 'redeem')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(-500, $tx->points);
    }

    public function test_throws_if_redeeming_more_than_balance(): void
    {
        $this->expectException(ValidationException::class);

        $this->loyaltyService->earnPoints($this->user, 100, 'order', 1);
        $this->loyaltyService->redeemPoints($this->user, 500, orderId: 99, orderTotalFils: 50000);
    }

    public function test_throws_if_redemption_exceeds_max_percent_of_order(): void
    {
        $this->expectException(ValidationException::class);

        // max_redeem_percent=0.20, order=10000 fils → max discount = 2000 fils = 200 points
        // Trying to redeem 500 points → 5000 fils → exceeds 2000 fils cap
        $this->loyaltyService->earnPoints($this->user, 5000, 'order', 1);
        $this->loyaltyService->redeemPoints($this->user, 500, orderId: 99, orderTotalFils: 10000);
    }

    // -----------------------------------------------------------------------
    // getBalance / getHistory
    // -----------------------------------------------------------------------

    public function test_returns_correct_balance(): void
    {
        $this->loyaltyService->earnPoints($this->user, 1000, 'order', 1);
        $this->assertEquals(1000, $this->loyaltyService->getBalance($this->user));
    }

    public function test_returns_transaction_history(): void
    {
        $this->loyaltyService->earnPoints($this->user, 1000, 'order', 1);
        $this->loyaltyService->earnPoints($this->user, 500, 'order', 2);

        $history = $this->loyaltyService->getHistory($this->user);
        $this->assertCount(2, $history);
    }

    // -----------------------------------------------------------------------
    // creditStoreCredit
    // -----------------------------------------------------------------------

    public function test_converts_fils_to_points_and_credits_as_store_credit(): void
    {
        // fils_per_point=10, so 5000 fils → 500 points
        $this->loyaltyService->creditStoreCredit($this->user, 5000, referenceType: 'return', referenceId: 1);

        $account = LoyaltyAccount::where('user_id', $this->user->id)->first();
        $this->assertEquals(500, $account->points_balance);

        $tx = LoyaltyTransaction::first();
        $this->assertEquals('store_credit', $tx->type);
        $this->assertEquals(500, $tx->points);
    }

    // -----------------------------------------------------------------------
    // manualAdjust
    // -----------------------------------------------------------------------

    public function test_allows_positive_manual_adjustment(): void
    {
        $admin = User::factory()->create();
        $this->loyaltyService->manualAdjust($this->user, 200, 'Bonus for loyal customer', 'مكافأة للعميل', $admin);

        $account = LoyaltyAccount::where('user_id', $this->user->id)->first();
        $this->assertEquals(200, $account->points_balance);

        $tx = LoyaltyTransaction::first();
        $this->assertEquals('adjust', $tx->type);
        $this->assertEquals(200, $tx->points);
        $this->assertEquals('admin', $tx->reference_type);
    }

    public function test_allows_negative_manual_adjustment(): void
    {
        $admin = User::factory()->create();
        $this->loyaltyService->earnPoints($this->user, 500, 'order', 1);
        $this->loyaltyService->manualAdjust($this->user, -100, 'Correction', 'تصحيح', $admin);

        $account = LoyaltyAccount::where('user_id', $this->user->id)->first();
        $this->assertEquals(400, $account->points_balance);
    }

    // -----------------------------------------------------------------------
    // EarnPointsJob integration
    // -----------------------------------------------------------------------

    public function test_earn_points_job_is_dispatched_on_payment_captured(): void
    {
        Queue::fake();

        $order = $this->makeOrder(subtotalFils: 10000);
        PaymentCaptured::dispatch($order);

        Queue::assertPushed(EarnPointsJob::class, fn ($job) => $job->order->id === $order->id);
    }

    public function test_earn_points_job_credits_points_based_on_order_subtotal(): void
    {
        $order = $this->makeOrder(subtotalFils: 10000);

        // Run job directly (not via queue)
        (new EarnPointsJob($order))->handle($this->loyaltyService);

        $this->assertEquals(10000, $this->loyaltyService->getBalance($order->user));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeOrder(int $subtotalFils): Order
    {
        $category = Category::create(['name' => ['ar' => 'قسم', 'en' => 'Cat'], 'slug' => 'cat-'.uniqid()]);
        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Prod'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => $subtotalFils,
            'is_available' => true,
            'product_type' => 'simple',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-'.uniqid(),
            'price_fils' => $subtotalFils,
            'attributes' => [],
        ]);
        InventoryItem::create(['variant_id' => $variant->id, 'quantity_available' => 10, 'quantity_reserved' => 0]);

        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $this->user->id,
            'order_status' => 'paid',
            'subtotal_fils' => $subtotalFils,
            'vat_fils' => (int) ($subtotalFils * 0.1),
            'total_fils' => (int) ($subtotalFils * 1.1),
            'payment_method' => 'tap',
        ]);
    }
}
