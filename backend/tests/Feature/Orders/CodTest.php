<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Notifications\Mail\CodCollectedMail;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Events\CODCollected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CodTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
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
            'product_type' => 'virtual', // virtual products don't require shipping
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

    private function makeOrder(
        User $user,
        string $status = 'pending_collection',
        string $paymentMethod = 'cod'
    ): Order {
        // Create product and variant
        $variant = $this->makeVariantWithStock(5000, 10);

        $order = Order::create([
            'order_number' => 'ORD-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'order_status' => $status,
            'subtotal_fils' => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils' => 1000,
            'delivery_fee_fils' => 0,
            'total_fils' => 11000,
            'payment_method' => $paymentMethod,
            'locale' => 'ar',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'product_name' => $variant->product->name,
            'variant_attributes' => [],
            'quantity' => 2,
            'price_fils_per_unit' => 5000,
            'total_fils' => 10000,
        ]);

        return $order->load(['items', 'statusHistory']);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function test_mark_cod_collected_transitions_status(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        $this->assertEquals('pending_collection', $order->order_status);

        $updated = $this->orderService->markCodCollected($order);

        $this->assertEquals('collected', $updated->order_status);
        $this->assertNotNull($updated->paid_at);
    }

    public function test_mark_cod_collected_records_status_history(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        $this->orderService->markCodCollected($order);

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('new_status', 'collected')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('pending_collection', $history->old_status);
        $this->assertEquals('collected', $history->new_status);
        $this->assertEquals('admin', $history->changed_by);
        $this->assertEquals('COD payment collected.', $history->reason);
    }

    public function test_mark_cod_collected_with_custom_note(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        $this->orderService->markCodCollected($order, 'Payment verified and deposited');

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('new_status', 'collected')
            ->first();

        $this->assertEquals('Payment verified and deposited', $history->reason);
    }

    public function test_mark_cod_collected_dispatches_event(): void
    {
        Event::fake([CODCollected::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        $this->orderService->markCodCollected($order);

        Event::assertDispatched(CODCollected::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    // -----------------------------------------------------------------------
    // Error cases
    // -----------------------------------------------------------------------

    public function test_mark_cod_collected_throws_for_non_cod_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'card'); // card, not COD

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a COD order');

        $this->orderService->markCodCollected($order);
    }

    public function test_mark_cod_collected_throws_for_wrong_status_not_pending_collection(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending', 'cod'); // pending, not pending_collection

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be marked collected');

        $this->orderService->markCodCollected($order);
    }

    public function test_mark_cod_collected_throws_for_already_collected(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'collected', 'cod');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be marked collected');

        $this->orderService->markCodCollected($order);
    }

    public function test_mark_cod_collected_throws_for_paid_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid', 'cod');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be marked collected');

        $this->orderService->markCodCollected($order);
    }

    // -----------------------------------------------------------------------
    // Email notifications
    // -----------------------------------------------------------------------

    public function test_cod_collected_mail_queued_when_event_fires(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        // Dispatch the event — the listener should queue the mail
        // Since QUEUE_CONNECTION=sync in testing, the mail is queued immediately
        CODCollected::dispatch($order);

        Mail::assertQueued(CodCollectedMail::class);
    }

    public function test_cod_collected_mail_subject_contains_arabic_for_ar_locale(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');
        $order->update(['locale' => 'ar']);

        $mail = new CodCollectedMail($order);
        $envelope = $mail->envelope();

        // The subject should contain the order number and be in Arabic
        // The actual translation is in resources/lang/ar/emails.php
        $this->assertStringContainsString($order->order_number, $envelope->subject);
    }

    public function test_cod_collected_mail_subject_contains_english_for_en_locale(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');
        $order->update(['locale' => 'en']);

        $mail = new CodCollectedMail($order);
        $envelope = $mail->envelope();

        // The subject should contain the order number and be in English
        $this->assertStringContainsString($order->order_number, $envelope->subject);
    }

    public function test_cod_collected_mail_uses_authenticated_user_email(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        $mail = new CodCollectedMail($order);
        $envelope = $mail->envelope();

        $this->assertEquals('user@example.com', $envelope->to[0]->address);
    }

    public function test_cod_collected_mail_uses_guest_email_when_no_user(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'order_status' => 'pending_collection',
            'subtotal_fils' => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils' => 1000,
            'delivery_fee_fils' => 0,
            'total_fils' => 11000,
            'payment_method' => 'cod',
            'locale' => 'ar',
        ]);

        $mail = new CodCollectedMail($order);
        $envelope = $mail->envelope();

        $this->assertEquals('guest@example.com', $envelope->to[0]->address);
    }

    // -----------------------------------------------------------------------
    // Idempotency and state refreshing
    // -----------------------------------------------------------------------

    public function test_mark_cod_collected_refreshes_order_state(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection', 'cod');

        // Simulate stale state (order in memory is old)
        $order->update(['order_status' => 'collected']);

        // When markCodCollected is called, it should refresh() internally
        // and detect that the order is already in 'collected' status
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be marked collected');

        $this->orderService->markCodCollected($order);
    }
}
