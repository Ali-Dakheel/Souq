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
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Services\InvoiceService;
use App\Modules\Settings\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoiceService = app(InvoiceService::class);
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
        int $subtotalFils = 10000,
        int $discountFils = 0,
        string $status = 'paid'
    ): Order {
        $vatFils = (int) round(($subtotalFils - $discountFils) * 0.10);
        $totalFils = $subtotalFils - $discountFils + $vatFils;

        // Create product and variant
        $variant = $this->makeVariantWithStock($subtotalFils / 2, 10);

        $order = Order::create([
            'order_number' => 'ORD-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'order_status' => $status,
            'subtotal_fils' => $subtotalFils,
            'coupon_discount_fils' => $discountFils,
            'vat_fils' => $vatFils,
            'delivery_fee_fils' => 0,
            'total_fils' => $totalFils,
            'payment_method' => 'card',
            'locale' => 'ar',
        ]);

        // Create a minimal order item
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'product_name' => $variant->product->name,
            'variant_attributes' => [],
            'quantity' => 2,
            'price_fils_per_unit' => $subtotalFils / 2,
            'total_fils' => $subtotalFils,
        ]);

        return $order->load(['items', 'items.variant.product']);
    }

    // -----------------------------------------------------------------------
    // Happy path — invoice generation
    // -----------------------------------------------------------------------

    public function test_invoice_generated_correctly_for_paid_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $invoice = $this->invoiceService->generateInvoice($order);

        $this->assertNotNull($invoice->id);
        $this->assertEquals($order->id, $invoice->order_id);
        $this->assertStringStartsWith('INV-2026-', $invoice->invoice_number);
        $this->assertEquals(10000, $invoice->subtotal_fils);
        $this->assertEquals(0, $invoice->discount_fils);
        $this->assertEquals(1000, $invoice->vat_fils); // 10% of 10000
        $this->assertEquals(11000, $invoice->total_fils); // 10000 + 1000
        $this->assertEquals('CR-00000000', $invoice->cr_number);
        $this->assertEquals('VAT-000000000', $invoice->vat_number);
    }

    public function test_invoice_total_derived_correctly_with_discount(): void
    {
        $user = User::factory()->create();
        // Subtotal 10000, discount 1000, so taxable base = 9000
        // VAT = 900, total = 9000 + 900 = 9900
        $order = $this->makeOrder($user, 10000, 1000, 'paid');

        $invoice = $this->invoiceService->generateInvoice($order);

        $this->assertEquals(10000, $invoice->subtotal_fils);
        $this->assertEquals(1000, $invoice->discount_fils);
        $this->assertEquals(900, $invoice->vat_fils); // 10% of (10000 - 1000)
        $this->assertEquals(9900, $invoice->total_fils); // (10000 - 1000) + 900
    }

    public function test_invoice_items_created_with_correct_quantities(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $invoice = $this->invoiceService->generateInvoice($order);

        $items = $invoice->items()->get();
        $this->assertCount(1, $items);
        $this->assertEquals(2, $items[0]->quantity);
        $this->assertEquals(5000, $items[0]->unit_price_fils); // 10000 / 2
    }

    public function test_invoice_items_vat_computed_from_unit_price(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $invoice = $this->invoiceService->generateInvoice($order);

        $items = $invoice->items()->get();
        $item = $items[0];

        // unit_price_fils = 5000 (VAT-exclusive)
        // quantity = 2
        // item_subtotal = 5000 * 2 = 10000
        // item_vat = 10000 * 0.10 = 1000
        // item_total = 10000 + 1000 = 11000
        $this->assertEquals(5000, $item->unit_price_fils);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(10, $item->vat_rate); // Stored as integer percentage
        $this->assertEquals(1000, $item->vat_fils);
        $this->assertEquals(11000, $item->total_fils);
    }

    public function test_invoice_snapshots_cr_and_vat_numbers(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $invoice = $this->invoiceService->generateInvoice($order);

        // Should be seeded by TestCase::setUp()
        $this->assertEquals('CR-00000000', $invoice->cr_number);
        $this->assertEquals('VAT-000000000', $invoice->vat_number);
    }

    // -----------------------------------------------------------------------
    // Idempotency
    // -----------------------------------------------------------------------

    public function test_invoice_is_idempotent_returns_existing(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $inv1 = $this->invoiceService->generateInvoice($order);
        $inv2 = $this->invoiceService->generateInvoice($order);

        $this->assertEquals($inv1->id, $inv2->id);
        $this->assertEquals($inv1->invoice_number, $inv2->invoice_number);
        $this->assertDatabaseCount('invoices', 1);
    }

    // -----------------------------------------------------------------------
    // Error cases
    // -----------------------------------------------------------------------

    public function test_invoice_generation_throws_when_cr_number_empty(): void
    {
        // Remove CR number from store settings
        StoreSetting::where('key', 'cr_number')->update(['value' => '']);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CR number and VAT number must be configured');

        $this->invoiceService->generateInvoice($order);
    }

    public function test_invoice_generation_throws_when_vat_number_empty(): void
    {
        // Remove VAT number from store settings
        StoreSetting::where('key', 'vat_number')->update(['value' => '']);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CR number and VAT number must be configured');

        $this->invoiceService->generateInvoice($order);
    }

    public function test_invoice_generation_throws_when_both_empty(): void
    {
        // Remove both
        StoreSetting::where('key', 'cr_number')->update(['value' => '']);
        StoreSetting::where('key', 'vat_number')->update(['value' => '']);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CR number and VAT number must be configured');

        $this->invoiceService->generateInvoice($order);
    }

    // -----------------------------------------------------------------------
    // API endpoints
    // -----------------------------------------------------------------------

    public function test_get_invoice_endpoint_returns_200_for_owner(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');
        $this->invoiceService->generateInvoice($order);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}/invoice");

        $response->assertStatus(200);
        $response->assertJsonPath('data.invoice_number', $order->invoice->invoice_number);
        $response->assertJsonPath('data.subtotal_fils', 10000);
        $response->assertJsonPath('data.vat_fils', 1000);
        $response->assertJsonPath('data.total_fils', 11000);
    }

    public function test_get_invoice_endpoint_returns_404_when_not_generated(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');
        // Don't generate invoice

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}/invoice");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Invoice not yet generated.');
    }

    public function test_get_invoice_endpoint_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->makeOrder($owner, 10000, 0, 'paid');
        $this->invoiceService->generateInvoice($order);

        $response = $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}/invoice");

        $response->assertStatus(403);
    }

    public function test_get_invoice_endpoint_requires_authentication(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 10000, 0, 'paid');
        $this->invoiceService->generateInvoice($order);

        $response = $this->getJson("/api/v1/orders/{$order->order_number}/invoice");

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Event dispatch
    // -----------------------------------------------------------------------

    public function test_payment_captured_event_triggers_generate_invoice_job(): void
    {
        Event::fake();

        Event::fakeExcept('Illuminate\Events\Dispatcher\Providers\EventServiceProvider');

        $user = User::factory()->create();
        $variant = $this->makeVariantWithStock(5000, 10);
        $addr = $this->makeAddress($user);

        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(30)]);
        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price_fils_snapshot' => 5000,
        ]);

        // Checkout will dispatch OrderPlaced, which is faked
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'shipping_address_id' => $addr->id,
                'billing_address_id' => $addr->id,
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(201);

        // For COD orders, GenerateInvoiceJob is dispatched in OrderService::checkout()
        // Since QUEUE_CONNECTION=sync in testing, the invoice should be created immediately
        $orderId = $response->json('data.id');
        $order = Order::find($orderId);

        // Invoice should exist because the job was queued and executed synchronously
        $this->assertNotNull($order->invoice);
        $this->assertEquals(5000, $order->invoice->subtotal_fils);
        $this->assertEquals(500, $order->invoice->vat_fils);
        $this->assertEquals(5500, $order->invoice->total_fils);
    }

    // -----------------------------------------------------------------------
    // Invoice number sequence
    // -----------------------------------------------------------------------

    public function test_invoice_numbers_are_sequential(): void
    {
        $user = User::factory()->create();

        $order1 = $this->makeOrder($user, 5000, 0, 'paid');
        $inv1 = $this->invoiceService->generateInvoice($order1);

        $order2 = $this->makeOrder($user, 6000, 0, 'paid');
        $inv2 = $this->invoiceService->generateInvoice($order2);

        $order3 = $this->makeOrder($user, 7000, 0, 'paid');
        $inv3 = $this->invoiceService->generateInvoice($order3);

        // Invoice numbers should be INV-2026-000001, INV-2026-000002, INV-2026-000003
        $this->assertStringEndsWith('-000001', $inv1->invoice_number);
        $this->assertStringEndsWith('-000002', $inv2->invoice_number);
        $this->assertStringEndsWith('-000003', $inv3->invoice_number);
    }
}
