<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDetailTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeOrder(
        ?User $user = null,
        string $status = 'pending',
        ?string $guestEmail = null,
    ): Order {
        return Order::create([
            'order_number' => 'ORD-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $user?->id,
            'guest_email' => $guestEmail,
            'order_status' => $status,
            'subtotal_fils' => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils' => 1000,
            'delivery_fee_fils' => 0,
            'total_fils' => 11000,
            'payment_method' => 'card',
        ]);
    }

    // -----------------------------------------------------------------------
    // Authenticated owner
    // -----------------------------------------------------------------------

    public function test_owner_can_view_their_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}");

        $response->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.order_status', 'pending')
            ->assertJsonPath('data.total_fils', 11000);
    }

    // -----------------------------------------------------------------------
    // 403 — wrong user
    // -----------------------------------------------------------------------

    public function test_different_user_receives_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->makeOrder($owner);

        $response = $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}");

        $response->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // 404 — non-existent order
    // -----------------------------------------------------------------------

    public function test_not_found_for_non_existent_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders/ORD-9999-99999');

        $response->assertStatus(404);
    }

    // -----------------------------------------------------------------------
    // Status history included
    // -----------------------------------------------------------------------

    public function test_response_includes_status_history(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'old_status' => 'pending',
            'new_status' => 'pending',
            'changed_by' => 'system',
            'reason' => 'Order placed.',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->order_number}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.status_history');
    }

    // -----------------------------------------------------------------------
    // Guest order lookup
    // -----------------------------------------------------------------------

    public function test_guest_can_view_order_with_correct_email(): void
    {
        $order = $this->makeOrder(null, 'pending', 'guest@example.com');

        $response = $this->getJson(
            "/api/v1/orders/{$order->order_number}/guest?email=guest@example.com"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_guest_order_lookup_fails_with_wrong_email(): void
    {
        $order = $this->makeOrder(null, 'pending', 'real@example.com');

        $response = $this->getJson(
            "/api/v1/orders/{$order->order_number}/guest?email=wrong@example.com"
        );

        $response->assertStatus(401);
    }

    public function test_guest_endpoint_rejects_orders_with_no_guest_email(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending', null);

        $response = $this->getJson(
            "/api/v1/orders/{$order->order_number}/guest?email=someone@example.com"
        );

        $response->assertStatus(404); // whereNotNull('guest_email') filters it out
    }

    // -----------------------------------------------------------------------
    // Unauthenticated
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_view_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        $this->getJson("/api/v1/orders/{$order->order_number}")->assertStatus(401);
    }
}
