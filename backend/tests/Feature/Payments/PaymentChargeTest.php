<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Exceptions\TapApiException;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Payments\Services\TapApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentChargeTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, string $status = 'pending'): Order
    {
        return Order::create([
            'order_number'    => 'ORD-2026-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id'         => $user->id,
            'order_status'    => $status,
            'subtotal_fils'   => 10000,
            'vat_fils'        => 1000,
            'total_fils'      => 11000,
            'payment_method'  => 'card',
        ]);
    }

    private function mockTapApi(array $chargeResponse): void
    {
        $mock = $this->mock(TapApiService::class);
        $mock->shouldReceive('createCharge')->once()->andReturn($chargeResponse);
        $mock->shouldReceive('createCustomer')->andReturn(['id' => 'cus_test_123']);
    }

    private function tapChargeResponse(string $chargeId = 'chg_test_123'): array
    {
        return [
            'id'     => $chargeId,
            'status' => 'INITIATED',
            'transaction' => [
                'url' => 'https://tap.company/pay/' . $chargeId,
            ],
        ];
    }

    public function test_authenticated_user_can_create_charge(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $this->mockTapApi($this->tapChargeResponse());

        $response = $this->actingAs($user)->postJson('/api/v1/payments/charge', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'order_id', 'tap_charge_id', 'status'],
            'redirect_url',
        ]);
        $response->assertJsonPath('data.status', 'initiated');
        $response->assertJsonPath('redirect_url', 'https://tap.company/pay/chg_test_123');

        $this->assertDatabaseHas('tap_transactions', [
            'order_id'      => $order->id,
            'tap_charge_id' => 'chg_test_123',
            'status'        => 'initiated',
            'attempt_number' => 1,
        ]);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        $response = $this->postJson('/api/v1/payments/charge', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_pay_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->makeOrder($owner);

        $response = $this->actingAs($intruder)->postJson('/api/v1/payments/charge', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_pay_already_paid_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');

        $response = $this->actingAs($user)->postJson('/api/v1/payments/charge', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_retry_failed_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'failed');

        // Existing failed transaction
        TapTransaction::create([
            'order_id'       => $order->id,
            'tap_charge_id'  => 'chg_old',
            'amount_fils'    => 11000,
            'status'         => 'failed',
            'attempt_number' => 1,
        ]);

        $this->mockTapApi($this->tapChargeResponse('chg_retry'));

        $response = $this->actingAs($user)->postJson('/api/v1/payments/charge', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.attempt_number', 2);
    }

    public function test_tap_api_failure_returns_422(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        $mock = $this->mock(TapApiService::class);
        $mock->shouldReceive('createCharge')->once()->andThrow(new TapApiException('Card declined', 400));
        $mock->shouldReceive('createCustomer')->andReturn(['id' => 'cus_test_123']);

        $response = $this->actingAs($user)->postJson('/api/v1/payments/charge', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('tap_transactions', ['order_id' => $order->id]);
    }

    public function test_order_id_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/payments/charge', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_id');
    }
}
