<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'paid'): Order
    {
        $user = User::create([
            'name' => 'Customer',
            'email' => 'c@test.com',
            'password' => bcrypt('pw'),
        ]);

        return Order::create([
            'order_number' => 'ORD-2026-001',
            'user_id' => $user->id,
            'order_status' => $status,
            'subtotal_fils' => 10000,
            'vat_fils' => 1000,
            'total_fils' => 11000,
            'payment_method' => 'card',
            'locale' => 'ar',
        ]);
    }

    public function test_fulfill_order_sets_status_and_fires_event(): void
    {
        Event::fake([OrderFulfilled::class]);
        $order = $this->makeOrder('paid');
        $service = app(OrderService::class);

        $service->fulfillOrder($order, 'TRK-123');

        $order->refresh();
        $this->assertEquals('fulfilled', $order->order_status);
        $this->assertEquals('TRK-123', $order->tracking_number);
        $this->assertNotNull($order->fulfilled_at);

        Event::assertDispatched(OrderFulfilled::class, fn ($e) => $e->order->id === $order->id);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'new_status' => 'fulfilled',
        ]);
    }

    public function test_override_order_status_records_history(): void
    {
        $order = $this->makeOrder('paid');
        $service = app(OrderService::class);

        $service->overrideOrderStatus($order, 'processing', 'Moving to processing');

        $order->refresh();
        $this->assertEquals('processing', $order->order_status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'new_status' => 'processing',
            'reason' => 'Moving to processing',
        ]);
    }

    public function test_cancel_order_as_admin_fires_event(): void
    {
        Event::fake([OrderCancelled::class]);
        $order = $this->makeOrder('pending');
        $service = app(OrderService::class);

        $service->cancelOrderAsAdmin($order);

        $order->refresh();
        $this->assertEquals('cancelled', $order->order_status);
        Event::assertDispatched(OrderCancelled::class);
    }
}
