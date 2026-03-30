<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

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
            'payment_method'       => 'benefit',
        ]);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function test_can_cancel_pending_order(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.order_status', 'cancelled');
    }

    public function test_can_cancel_initiated_order(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'initiated');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.order_status', 'cancelled');
    }

    public function test_cancel_sets_cancelled_at_timestamp(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel")
            ->assertStatus(200);

        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    public function test_cancel_reason_is_optional(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel", [
                'reason' => 'Changed my mind',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order_status', 'cancelled');
    }

    // -----------------------------------------------------------------------
    // Business rules — non-cancellable statuses
    // -----------------------------------------------------------------------

    public function test_cannot_cancel_paid_order(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel");

        $response->assertStatus(422);
        Event::assertNotDispatched(OrderCancelled::class);
    }

    public function test_cannot_cancel_failed_order(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'failed');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel")
            ->assertStatus(422);
    }

    public function test_cannot_cancel_already_cancelled_order(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'cancelled');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel")
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------------

    public function test_cannot_cancel_another_users_order(): void
    {
        Event::fake([OrderCancelled::class]);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->makeOrder($owner, 'pending');

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel")
            ->assertStatus(403);

        Event::assertNotDispatched(OrderCancelled::class);
    }

    // -----------------------------------------------------------------------
    // Events and history
    // -----------------------------------------------------------------------

    public function test_cancel_fires_order_cancelled_event(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel", [
                'reason' => 'Test reason',
            ])
            ->assertStatus(200);

        Event::assertDispatched(OrderCancelled::class, function (OrderCancelled $e) use ($order) {
            return $e->order->id === $order->id
                && $e->reason === 'Test reason';
        });
    }

    public function test_cancel_records_status_history_entry(): void
    {
        Event::fake([OrderCancelled::class]);

        $user  = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/cancel", [
                'reason' => 'No longer needed',
            ])
            ->assertStatus(200);

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('new_status', 'cancelled')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('pending', $history->old_status);
        $this->assertEquals('customer', $history->changed_by);
        $this->assertEquals('No longer needed', $history->reason);
    }
}
