<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    private InventoryMovementService $movementService;

    private Variant $variant;

    private InventoryItem $inventoryItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->movementService = app(InventoryMovementService::class);

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
        $this->inventoryItem = InventoryItem::create([
            'variant_id' => $this->variant->id,
            'quantity_available' => 20,
            'quantity_reserved' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // InventoryMovementService::record()
    // -----------------------------------------------------------------------

    public function test_records_a_movement_with_all_fields(): void
    {
        $movement = $this->movementService->record(
            variantId: $this->variant->id,
            type: 'manual_in',
            delta: 5,
            quantityAfter: 25,
            referenceType: 'admin',
            referenceId: null,
            notes: 'Stock replenishment',
            createdBy: null,
        );

        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertEquals($this->variant->id, $movement->variant_id);
        $this->assertEquals('manual_in', $movement->type);
        $this->assertEquals(5, $movement->quantity_delta);
        $this->assertEquals(25, $movement->quantity_after);
        $this->assertEquals('admin', $movement->reference_type);
        $this->assertEquals('Stock replenishment', $movement->notes);
    }

    public function test_records_manual_out_movement_with_negative_delta(): void
    {
        $movement = $this->movementService->record(
            variantId: $this->variant->id,
            type: 'manual_out',
            delta: -3,
            quantityAfter: 17,
        );

        $this->assertEquals(-3, $movement->quantity_delta);
        $this->assertEquals('manual_out', $movement->type);
    }

    public function test_records_reservation_when_order_placed(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'order_status' => 'pending',
            'subtotal_fils' => 5000,
            'vat_fils' => 500,
            'total_fils' => 5500,
            'payment_method' => 'tap',
        ]);

        OrderPlaced::dispatch($order, [
            ['variant_id' => $this->variant->id, 'quantity' => 2],
        ]);

        $movement = InventoryMovement::where('variant_id', $this->variant->id)
            ->where('type', 'reservation')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity_delta);
        $this->assertEquals('order', $movement->reference_type);
        $this->assertEquals($order->id, $movement->reference_id);
    }

    public function test_records_release_when_order_cancelled(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'order_status' => 'cancelled',
            'subtotal_fils' => 5000,
            'vat_fils' => 500,
            'total_fils' => 5500,
            'payment_method' => 'tap',
        ]);

        // First reserve so we have reserved stock
        $this->inventoryItem->increment('quantity_reserved', 2);

        OrderCancelled::dispatch($order, [
            ['variant_id' => $this->variant->id, 'quantity' => 2],
        ]);

        $movement = InventoryMovement::where('variant_id', $this->variant->id)
            ->where('type', 'release')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(2, $movement->quantity_delta);
    }

    public function test_quantity_after_is_snapshot_of_stock_at_time_of_movement(): void
    {
        $movement = $this->movementService->record(
            variantId: $this->variant->id,
            type: 'manual_in',
            delta: 10,
            quantityAfter: 30,
        );

        $this->assertEquals(30, $movement->quantity_after);

        // Even if inventory changes later, snapshot is preserved
        $this->inventoryItem->increment('quantity_available', 5);
        $movement->refresh();
        $this->assertEquals(30, $movement->quantity_after);
    }

    public function test_records_movement_with_created_by_user(): void
    {
        $admin = User::factory()->create();
        $movement = $this->movementService->record(
            variantId: $this->variant->id,
            type: 'manual_in',
            delta: 5,
            quantityAfter: 25,
            createdBy: $admin->id,
        );

        $this->assertEquals($admin->id, $movement->created_by);
    }

    public function test_scopes_movements_by_type(): void
    {
        $this->movementService->record($this->variant->id, 'manual_in', 5, 25);
        $this->movementService->record($this->variant->id, 'manual_out', -2, 23);
        $this->movementService->record($this->variant->id, 'reservation', -1, 22);

        $manualMovements = InventoryMovement::where('variant_id', $this->variant->id)
            ->whereIn('type', ['manual_in', 'manual_out'])
            ->count();

        $this->assertEquals(2, $manualMovements);
    }

    public function test_multiple_movements_persist_chronologically(): void
    {
        $this->movementService->record($this->variant->id, 'manual_in', 10, 30);
        $this->movementService->record($this->variant->id, 'manual_out', -3, 27);

        $movements = InventoryMovement::where('variant_id', $this->variant->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertEquals(30, $movements->first()->quantity_after);
        $this->assertEquals(27, $movements->last()->quantity_after);
    }
}
