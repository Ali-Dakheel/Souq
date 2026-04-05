<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Returns\Events\ReturnApproved;
use App\Modules\Returns\Events\ReturnCompleted;
use App\Modules\Returns\Events\ReturnRejected;
use App\Modules\Returns\Events\ReturnRequested;
use App\Modules\Returns\Models\ReturnRequest;
use App\Modules\Returns\Models\ReturnRequestItem;
use App\Modules\Returns\Services\ReturnService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    private ReturnService $returnService;

    private User $user;

    private Order $deliveredOrder;

    private OrderItem $orderItem;

    private Variant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->returnService = app(ReturnService::class);
        $this->user = User::factory()->create();

        $category = Category::create(['name' => ['ar' => 'قسم', 'en' => 'Cat'], 'slug' => 'cat-'.uniqid()]);
        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Prod'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => 5000,
            'is_available' => true,
            'product_type' => 'simple',
        ]);
        $this->variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-'.uniqid(),
            'price_fils' => 5000,
            'attributes' => [],
        ]);
        InventoryItem::create([
            'variant_id' => $this->variant->id,
            'quantity_available' => 20,
            'quantity_reserved' => 0,
        ]);

        $this->deliveredOrder = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $this->user->id,
            'order_status' => 'delivered',
            'subtotal_fils' => 5000,
            'vat_fils' => 500,
            'total_fils' => 5500,
            'payment_method' => 'tap',
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'variant_id' => $this->variant->id,
            'quantity' => 2,
            'price_fils_per_unit' => 5000,
            'total_fils' => 10000,
            'product_name' => ['en' => 'Product', 'ar' => 'منتج'],
            'sku' => $this->variant->sku,
        ]);
    }

    // -----------------------------------------------------------------------
    // createRequest
    // -----------------------------------------------------------------------

    public function test_creates_a_pending_return_request_with_items(): void
    {
        $returnRequest = $this->returnService->createRequest(
            order: $this->deliveredOrder,
            user: $this->user,
            reason: 'defective',
            notes: 'Item arrived broken',
            items: [
                ['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged'],
            ]
        );

        $this->assertInstanceOf(ReturnRequest::class, $returnRequest);
        $this->assertEquals('pending', $returnRequest->status);
        $this->assertEquals('defective', $returnRequest->reason);
        $this->assertEquals($this->deliveredOrder->id, $returnRequest->order_id);
        $this->assertEquals($this->user->id, $returnRequest->user_id);
        $this->assertCount(1, $returnRequest->items);
    }

    public function test_generates_sequential_rma_numbers(): void
    {
        $r1 = $this->returnService->createRequest(
            order: $this->deliveredOrder,
            user: $this->user,
            reason: 'defective',
            notes: null,
            items: [['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged']]
        );

        $order2 = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $this->user->id,
            'order_status' => 'delivered',
            'subtotal_fils' => 5000,
            'vat_fils' => 500,
            'total_fils' => 5500,
            'payment_method' => 'tap',
        ]);
        $item2 = OrderItem::create([
            'order_id' => $order2->id,
            'variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_fils_per_unit' => 5000,
            'total_fils' => 5000,
            'product_name' => ['en' => 'Product', 'ar' => 'منتج'],
            'sku' => $this->variant->sku,
        ]);

        $r2 = $this->returnService->createRequest(
            order: $order2,
            user: $this->user,
            reason: 'wrong_item',
            notes: null,
            items: [['order_item_id' => $item2->id, 'quantity_returned' => 1, 'condition' => 'unopened']]
        );

        $year = now()->year;
        $this->assertStringStartsWith("RMA-{$year}-", $r1->request_number);
        $this->assertStringStartsWith("RMA-{$year}-", $r2->request_number);
        $this->assertNotEquals($r1->request_number, $r2->request_number);

        // Sequential: r2 number is r1 + 1
        $seq1 = (int) substr($r1->request_number, -6);
        $seq2 = (int) substr($r2->request_number, -6);
        $this->assertEquals($seq1 + 1, $seq2);
    }

    public function test_fires_return_requested_event(): void
    {
        Event::fake([ReturnRequested::class]);

        $this->returnService->createRequest(
            order: $this->deliveredOrder,
            user: $this->user,
            reason: 'changed_mind',
            notes: null,
            items: [['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'unopened']]
        );

        Event::assertDispatched(ReturnRequested::class);
    }

    public function test_rejects_return_if_order_not_delivered(): void
    {
        $this->expectException(ValidationException::class);

        $pendingOrder = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $this->user->id,
            'order_status' => 'pending',
            'subtotal_fils' => 5000,
            'vat_fils' => 500,
            'total_fils' => 5500,
            'payment_method' => 'tap',
        ]);

        $item = OrderItem::create([
            'order_id' => $pendingOrder->id,
            'variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_fils_per_unit' => 5000,
            'total_fils' => 5000,
            'product_name' => ['en' => 'P', 'ar' => 'م'],
            'sku' => $this->variant->sku,
        ]);

        $this->returnService->createRequest(
            order: $pendingOrder,
            user: $this->user,
            reason: 'defective',
            notes: null,
            items: [['order_item_id' => $item->id, 'quantity_returned' => 1, 'condition' => 'damaged']]
        );
    }

    public function test_rejects_return_outside_14_day_window(): void
    {
        $this->expectException(ValidationException::class);

        // Make order delivered 15 days ago (forceFill bypasses mass-assignment)
        $this->deliveredOrder->forceFill(['created_at' => Carbon::now()->subDays(15)])->save();

        $this->returnService->createRequest(
            order: $this->deliveredOrder,
            user: $this->user,
            reason: 'defective',
            notes: null,
            items: [['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged']]
        );
    }

    public function test_rejects_return_if_order_belongs_to_different_user(): void
    {
        $this->expectException(ValidationException::class);

        $otherUser = User::factory()->create();

        $this->returnService->createRequest(
            order: $this->deliveredOrder,
            user: $otherUser,
            reason: 'defective',
            notes: null,
            items: [['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged']]
        );
    }

    // -----------------------------------------------------------------------
    // approveReturn
    // -----------------------------------------------------------------------

    public function test_approves_a_pending_return(): void
    {
        $returnRequest = $this->returnService->createRequest(
            order: $this->deliveredOrder,
            user: $this->user,
            reason: 'defective',
            notes: null,
            items: [['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged']]
        );

        $admin = User::factory()->create();
        $approved = $this->returnService->approveReturn(
            returnRequest: $returnRequest,
            admin: $admin,
            resolution: 'store_credit',
            resolutionAmountFils: 5000,
            adminNotes: 'Approved - confirmed defect'
        );

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals('store_credit', $approved->resolution);
        $this->assertEquals(5000, $approved->resolution_amount_fils);
        $this->assertEquals('Approved - confirmed defect', $approved->admin_notes);
    }

    public function test_fires_return_approved_event(): void
    {
        Event::fake([ReturnApproved::class]);

        $returnRequest = ReturnRequest::create([
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->user->id,
            'request_number' => 'RMA-2026-000001',
            'status' => 'pending',
            'reason' => 'defective',
        ]);

        $admin = User::factory()->create();
        $this->returnService->approveReturn($returnRequest, $admin, 'store_credit', 5000);

        Event::assertDispatched(ReturnApproved::class);
    }

    public function test_fails_to_approve_non_pending_return(): void
    {
        $this->expectException(ValidationException::class);

        $returnRequest = ReturnRequest::create([
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->user->id,
            'request_number' => 'RMA-2026-000001',
            'status' => 'approved',
            'reason' => 'defective',
        ]);

        $admin = User::factory()->create();
        $this->returnService->approveReturn($returnRequest, $admin, 'store_credit', 5000);
    }

    // -----------------------------------------------------------------------
    // rejectReturn
    // -----------------------------------------------------------------------

    public function test_rejects_a_pending_return_with_admin_notes(): void
    {
        $returnRequest = ReturnRequest::create([
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->user->id,
            'request_number' => 'RMA-2026-000001',
            'status' => 'pending',
            'reason' => 'changed_mind',
        ]);

        $admin = User::factory()->create();
        $rejected = $this->returnService->rejectReturn($returnRequest, $admin, 'Outside return window');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Outside return window', $rejected->admin_notes);
    }

    public function test_fires_return_rejected_event(): void
    {
        Event::fake([ReturnRejected::class]);

        $returnRequest = ReturnRequest::create([
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->user->id,
            'request_number' => 'RMA-2026-000001',
            'status' => 'pending',
            'reason' => 'changed_mind',
        ]);

        $admin = User::factory()->create();
        $this->returnService->rejectReturn($returnRequest, $admin, 'Not eligible');

        Event::assertDispatched(ReturnRejected::class);
    }

    // -----------------------------------------------------------------------
    // completeReturn
    // -----------------------------------------------------------------------

    public function test_completes_an_approved_return_and_restocks_inventory(): void
    {
        Event::fake([ReturnCompleted::class]);

        $returnRequest = ReturnRequest::create([
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->user->id,
            'request_number' => 'RMA-2026-000001',
            'status' => 'approved',
            'reason' => 'defective',
            'resolution' => 'store_credit',
            'resolution_amount_fils' => 5000,
        ]);
        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'quantity_returned' => 1,
            'condition' => 'damaged',
        ]);

        $inventoryBefore = InventoryItem::where('variant_id', $this->variant->id)->first()->quantity_available;

        $this->returnService->completeReturn($returnRequest);

        $inventoryAfter = InventoryItem::where('variant_id', $this->variant->id)->first()->quantity_available;
        $this->assertEquals($inventoryBefore + 1, $inventoryAfter);

        $returnRequest->refresh();
        $this->assertEquals('completed', $returnRequest->status);
        Event::assertDispatched(ReturnCompleted::class);
    }

    // -----------------------------------------------------------------------
    // API endpoints
    // -----------------------------------------------------------------------

    public function test_api_customer_can_submit_return_for_delivered_order(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/orders/{$this->deliveredOrder->order_number}/returns",
            [
                'reason' => 'defective',
                'notes' => 'Broken on arrival',
                'items' => [
                    ['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged'],
                ],
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.reason', 'defective');
    }

    public function test_api_customer_can_list_returns_for_their_order(): void
    {
        ReturnRequest::create([
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->user->id,
            'request_number' => 'RMA-2026-000001',
            'status' => 'pending',
            'reason' => 'defective',
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/v1/orders/{$this->deliveredOrder->order_number}/returns"
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_api_returns_422_when_order_not_delivered(): void
    {
        $pendingOrder = Order::create([
            'order_number' => 'ORD-PEND-'.uniqid(),
            'user_id' => $this->user->id,
            'order_status' => 'pending',
            'subtotal_fils' => 5000,
            'vat_fils' => 500,
            'total_fils' => 5500,
            'payment_method' => 'tap',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/orders/{$pendingOrder->order_number}/returns",
            [
                'reason' => 'defective',
                'notes' => null,
                'items' => [
                    ['order_item_id' => $this->orderItem->id, 'quantity_returned' => 1, 'condition' => 'damaged'],
                ],
            ]
        );

        $response->assertStatus(422);
    }
}
