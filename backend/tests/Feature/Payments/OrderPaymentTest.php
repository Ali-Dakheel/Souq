<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_latest_payment_for_order(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-2026-00001',
            'user_id' => $user->id,
            'order_status' => 'paid',
            'subtotal_fils' => 10000,
            'vat_fils' => 1000,
            'total_fils' => 11000,
            'payment_method' => 'card',
        ]);

        TapTransaction::create([
            'order_id' => $order->id,
            'tap_charge_id' => 'chg_attempt_1',
            'amount_fils' => 11000,
            'status' => 'failed',
            'attempt_number' => 1,
        ]);

        TapTransaction::create([
            'order_id' => $order->id,
            'tap_charge_id' => 'chg_attempt_2',
            'amount_fils' => 11000,
            'status' => 'captured',
            'attempt_number' => 2,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/payments/order/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.tap_charge_id', 'chg_attempt_2');
        $response->assertJsonPath('data.attempt_number', 2);
    }

    public function test_cannot_get_payment_for_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-2026-00001',
            'user_id' => $owner->id,
            'order_status' => 'paid',
            'subtotal_fils' => 10000,
            'vat_fils' => 1000,
            'total_fils' => 11000,
            'payment_method' => 'card',
        ]);

        $response = $this->actingAs($intruder)->getJson("/api/v1/payments/order/{$order->id}");

        $response->assertStatus(403);
    }

    public function test_returns_null_when_no_payment_exists(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-2026-00001',
            'user_id' => $user->id,
            'order_status' => 'pending',
            'subtotal_fils' => 10000,
            'vat_fils' => 1000,
            'total_fils' => 11000,
            'payment_method' => 'card',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/payments/order/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data', null);
    }
}
