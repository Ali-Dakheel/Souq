<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\ShipmentCreated;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\Shipment;
use App\Modules\Orders\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentService $shipmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shipmentService = app(ShipmentService::class);
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

    private function makeOrder(User $user, string $status = 'paid'): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'order_status' => $status,
            'subtotal_fils' => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils' => 1000,
            'delivery_fee_fils' => 0,
            'total_fils' => 11000,
            'payment_method' => 'card',
            'locale' => 'ar',
        ]);

        $variant = $this->makeVariantWithStock();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'product_name' => $variant->product->name,
            'variant_attributes' => [],
            'quantity' => 5,
            'price_fils_per_unit' => 2000,
            'total_fils' => 10000,
        ]);

        return $order->load(['items', 'items.shipmentItems']);
    }

    // -----------------------------------------------------------------------
    // Happy path — shipment creation
    // -----------------------------------------------------------------------

    public function test_shipment_created_successfully_for_paid_order(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $shipment = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 3,
                ],
            ],
            carrier: 'DHL',
            trackingNumber: 'TRACK123'
        );

        $this->assertNotNull($shipment->id);
        $this->assertEquals($order->id, $shipment->order_id);
        $this->assertStringStartsWith('SHP-2026-', $shipment->shipment_number);
        $this->assertEquals('DHL', $shipment->carrier);
        $this->assertEquals('TRACK123', $shipment->tracking_number);
        $this->assertEquals('pending', $shipment->status);

        $items = $shipment->items;
        $this->assertCount(1, $items);
        $this->assertEquals($item->id, $items[0]->order_item_id);
        $this->assertEquals(3, $items[0]->quantity_shipped);

        Event::assertDispatched(ShipmentCreated::class);
    }

    public function test_shipment_for_pending_collection_order(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending_collection');
        $item = $order->items->first();

        $shipment = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $this->assertNotNull($shipment->id);
        $this->assertEquals('pending', $shipment->status);
    }

    public function test_shipment_for_shipped_order(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'shipped');
        $item = $order->items->first();

        $shipment = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 1,
                ],
            ]
        );

        $this->assertNotNull($shipment->id);
    }

    // -----------------------------------------------------------------------
    // Validation — order status
    // -----------------------------------------------------------------------

    public function test_shipment_rejects_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');
        $item = $order->items->first();

        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 1,
                ],
            ]
        );
    }

    public function test_shipment_rejects_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'cancelled');
        $item = $order->items->first();

        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 1,
                ],
            ]
        );
    }

    // -----------------------------------------------------------------------
    // Validation — item ownership (cross-order injection guard)
    // -----------------------------------------------------------------------

    public function test_shipment_rejects_unknown_order_item_id(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');

        // Try to ship a non-existent item
        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => 999,
                    'quantity_shipped' => 1,
                ],
            ]
        );
    }

    public function test_shipment_rejects_item_from_different_order(): void
    {
        $user = User::factory()->create();
        $order1 = $this->makeOrder($user, 'paid');
        $order2 = $this->makeOrder($user, 'paid');

        $item2 = $order2->items->first();

        // Try to ship an item from order2 as if it belongs to order1
        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order1,
            [
                [
                    'order_item_id' => $item2->id,
                    'quantity_shipped' => 1,
                ],
            ]
        );
    }

    // -----------------------------------------------------------------------
    // Validation — quantity constraints
    // -----------------------------------------------------------------------

    public function test_shipment_rejects_zero_quantity(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 0,
                ],
            ]
        );
    }

    public function test_shipment_rejects_negative_quantity(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => -1,
                ],
            ]
        );
    }

    public function test_shipment_rejects_quantity_exceeding_available(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first(); // quantity = 5

        $this->expectException(ValidationException::class);

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 10, // exceeds available 5
                ],
            ]
        );
    }

    // -----------------------------------------------------------------------
    // Partial shipment and quantity_to_ship tracking
    // -----------------------------------------------------------------------

    public function test_partial_shipment_reduces_quantity_to_ship(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first(); // quantity = 5

        // Ship 2, so 3 remaining
        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $item->refresh();
        $this->assertEquals(5, $item->quantity);
        $this->assertEquals(2, $item->quantity_shipped);
        $this->assertEquals(3, $item->quantity_to_ship);
    }

    public function test_multiple_partial_shipments(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first(); // quantity = 5

        // Ship 2
        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        // Ship another 2
        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $item->refresh();
        $this->assertEquals(4, $item->quantity_shipped);
        $this->assertEquals(1, $item->quantity_to_ship);
    }

    public function test_all_items_shipped_updates_order_to_shipped(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first(); // quantity = 5

        // Ship all 5
        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 5,
                ],
            ]
        );

        $order->refresh();
        $this->assertEquals('shipped', $order->order_status);
    }

    // -----------------------------------------------------------------------
    // markShipped() method
    // -----------------------------------------------------------------------

    public function test_mark_shipped_updates_status_and_timestamp(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $shipment = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $this->assertEquals('pending', $shipment->status);
        $this->assertNull($shipment->shipped_at);

        $updated = $this->shipmentService->markShipped($shipment, 'TRACK-NEW');

        $this->assertEquals('shipped', $updated->status);
        $this->assertNotNull($updated->shipped_at);
        $this->assertEquals('TRACK-NEW', $updated->tracking_number);
    }

    public function test_mark_shipped_without_tracking_number(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $shipment = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $updated = $this->shipmentService->markShipped($shipment);

        $this->assertEquals('shipped', $updated->status);
        $this->assertNotNull($updated->shipped_at);
    }

    // -----------------------------------------------------------------------
    // markDelivered() method
    // -----------------------------------------------------------------------

    public function test_mark_delivered_on_single_shipment_fires_order_fulfilled(): void
    {
        Event::fake([ShipmentCreated::class, OrderFulfilled::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $shipment = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 5, // All items
                ],
            ]
        );

        $updated = $this->shipmentService->markDelivered($shipment);

        $this->assertEquals('delivered', $updated->status);
        $this->assertNotNull($updated->delivered_at);

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);

        Event::assertDispatched(OrderFulfilled::class);
    }

    public function test_mark_delivered_on_non_final_shipment_does_not_fire_event(): void
    {
        Event::fake([ShipmentCreated::class, OrderFulfilled::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        // Create two shipments
        $shipment1 = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $shipment2 = $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 3,
                ],
            ]
        );

        // Mark first as delivered — should NOT fire event (second still pending)
        $this->shipmentService->markDelivered($shipment1);

        $order->refresh();
        $this->assertEquals('shipped', $order->order_status); // not changed to delivered

        // Now mark second as delivered — should fire event
        Event::fake([OrderFulfilled::class]); // reset fake for this assertion
        $this->shipmentService->markDelivered($shipment2);

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);

        Event::assertDispatched(OrderFulfilled::class);
    }

    // -----------------------------------------------------------------------
    // API endpoints
    // -----------------------------------------------------------------------

    public function test_get_shipments_endpoint_returns_200_for_owner(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}/shipments");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.shipment_number', Shipment::first()->shipment_number);
    }

    public function test_get_shipments_endpoint_returns_403_for_non_owner(): void
    {
        Event::fake([ShipmentCreated::class]);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->makeOrder($owner, 'paid');
        $item = $order->items->first();

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $response = $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}/shipments");

        $response->assertStatus(403);
    }

    public function test_get_shipments_endpoint_requires_authentication(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 2,
                ],
            ]
        );

        $response = $this->getJson("/api/v1/orders/{$order->order_number}/shipments");

        $response->assertStatus(401);
    }

    public function test_get_shipments_returns_shipment_items(): void
    {
        Event::fake([ShipmentCreated::class]);

        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $this->shipmentService->createShipment(
            $order,
            [
                [
                    'order_item_id' => $item->id,
                    'quantity_shipped' => 3,
                ],
            ]
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}/shipments");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.items.0.quantity_shipped', 3);
    }
}
