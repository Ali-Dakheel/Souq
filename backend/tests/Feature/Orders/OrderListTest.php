<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderListTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeOrder(User $user, string $status = 'pending', array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'         => 'ORD-2026-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id'              => $user->id,
            'order_status'         => $status,
            'subtotal_fils'        => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils'             => 1000,
            'delivery_fee_fils'    => 0,
            'total_fils'           => 11000,
            'payment_method'       => 'benefit',
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_user_can_list_their_orders_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->makeOrder($user);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'order_number', 'order_status', 'total_fils', 'created_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_unauthenticated_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    public function test_user_only_sees_their_own_orders(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->makeOrder($userA);
        $this->makeOrder($userA);
        $this->makeOrder($userB); // should not appear in userA's list

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_status_filter_returns_matching_orders_only(): void
    {
        $user = User::factory()->create();

        $this->makeOrder($user, 'pending');
        $this->makeOrder($user, 'pending');
        $this->makeOrder($user, 'paid');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $order) {
            $this->assertEquals('pending', $order['order_status']);
        }
    }

    public function test_orders_are_sorted_newest_first(): void
    {
        $user = User::factory()->create();

        $first  = $this->makeOrder($user);
        $second = $this->makeOrder($user);
        $third  = $this->makeOrder($user);

        // Touch timestamps to ensure order
        $first->update(['created_at' => now()->subMinutes(10)]);
        $second->update(['created_at' => now()->subMinutes(5)]);
        $third->update(['created_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $orderNumbers = array_column($response->json('data'), 'order_number');
        $this->assertEquals($third->order_number, $orderNumbers[0]);
        $this->assertEquals($first->order_number, $orderNumbers[2]);
    }

    public function test_empty_list_returned_when_user_has_no_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
