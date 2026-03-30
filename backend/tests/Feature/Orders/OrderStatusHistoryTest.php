<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeVariantWithStock(int $priceFils = 5000, int $stock = 10): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-' . uniqid(),
        ]);

        $product = Product::create([
            'name'           => ['ar' => 'منتج', 'en' => 'Product'],
            'slug'           => 'prod-' . uniqid(),
            'category_id'    => $category->id,
            'base_price_fils' => $priceFils,
            'is_available'   => true,
        ]);

        $variant = Variant::create([
            'product_id'   => $product->id,
            'sku'          => 'SKU-' . uniqid(),
            'attributes'   => [],
            'is_available' => true,
        ]);

        InventoryItem::create([
            'variant_id'        => $variant->id,
            'quantity_available' => $stock,
            'quantity_reserved'  => 0,
        ]);

        return $variant->load(['product', 'inventory']);
    }

    private function makeAddress(User $user): CustomerAddress
    {
        return CustomerAddress::create([
            'user_id'        => $user->id,
            'address_type'   => 'shipping',
            'recipient_name' => 'Ali Test',
            'phone'          => '+97366000000',
            'governorate'    => 'Capital',
            'district'       => 'Manama',
            'street_address' => '123 Test St',
            'is_default'     => true,
            'is_active'      => true,
        ]);
    }

    private function makeOrder(User $user, string $status = 'pending'): Order
    {
        return Order::create([
            'order_number'         => 'ORD-2026-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id'              => $user->id,
            'order_status'         => $status,
            'subtotal_fils'        => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils'             => 1000,
            'delivery_fee_fils'    => 0,
            'total_fils'           => 11000,
            'payment_method'       => 'card',
        ]);
    }

    // -----------------------------------------------------------------------
    // History recorded on checkout
    // -----------------------------------------------------------------------

    public function test_status_history_is_recorded_on_checkout(): void
    {
        Event::fake([OrderPlaced::class]);

        $user    = User::factory()->create();
        $variant = $this->makeVariantWithStock();
        $addr    = $this->makeAddress($user);

        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(30)]);
        CartItem::create([
            'cart_id'             => $cart->id,
            'variant_id'          => $variant->id,
            'quantity'            => 1,
            'price_fils_snapshot' => 5000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'shipping_address_id' => $addr->id,
                'billing_address_id'  => $addr->id,
                'payment_method'      => 'card',
            ])
            ->assertStatus(201);

        $orderId = $response->json('data.id');
        $history = OrderStatusHistory::where('order_id', $orderId)
            ->where('new_status', 'pending')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('system', $history->changed_by);
        $this->assertEquals('Order placed.', $history->reason);
    }

    // -----------------------------------------------------------------------
    // History recorded on cancel
    // -----------------------------------------------------------------------

    public function test_status_history_is_recorded_on_cancel(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel", [
                'reason' => 'Testing cancel history',
            ])
            ->assertStatus(200);

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('new_status', 'cancelled')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('pending', $history->old_status);
        $this->assertEquals('cancelled', $history->new_status);
        $this->assertEquals('Testing cancel history', $history->reason);
    }

    // -----------------------------------------------------------------------
    // History recorded on payment events (via OrderService directly)
    // -----------------------------------------------------------------------

    public function test_status_history_is_recorded_on_mark_paid(): void
    {
        $user     = User::factory()->create();
        $order    = $this->makeOrder($user, 'pending');
        $service  = app(OrderService::class);

        $order->update(['order_status' => 'paid', 'paid_at' => now()]);
        $service->recordStatusChange($order, 'paid', 'system', 'Payment captured.');

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('new_status', 'paid')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('Payment captured.', $history->reason);
    }

    // -----------------------------------------------------------------------
    // History fields are fully populated
    // -----------------------------------------------------------------------

    public function test_history_entry_has_all_required_fields(): void
    {
        $user    = User::factory()->create();
        $order   = $this->makeOrder($user, 'pending');
        $service = app(OrderService::class);

        $service->recordStatusChange($order, 'initiated', 'system', 'Tap charge created.');

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();

        $this->assertNotNull($history->order_id);
        $this->assertNotNull($history->old_status);
        $this->assertNotNull($history->new_status);
        $this->assertNotNull($history->changed_by);
        $this->assertNotNull($history->reason);
        $this->assertNotNull($history->created_at);
    }

    // -----------------------------------------------------------------------
    // Multiple entries accumulate (append-only / immutable)
    // -----------------------------------------------------------------------

    public function test_multiple_status_changes_accumulate(): void
    {
        $user    = User::factory()->create();
        $order   = $this->makeOrder($user, 'pending');
        $service = app(OrderService::class);

        $service->recordStatusChange($order, 'initiated', 'system', 'Redirected to Tap.');
        $order->update(['order_status' => 'paid', 'paid_at' => now()]);
        $service->recordStatusChange($order, 'paid', 'system', 'Payment captured.');

        $this->assertEquals(2, OrderStatusHistory::where('order_id', $order->id)->count());
    }
}
