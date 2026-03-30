<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Events\PaymentFailed;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Payments\Services\TapApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentResultTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithTransaction(
        User $user,
        string $tapChargeId = 'chg_test_123',
        string $orderStatus = 'initiated',
        string $txStatus = 'initiated',
    ): array {
        $order = Order::create([
            'order_number'  => 'ORD-2026-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id'       => $user->id,
            'order_status'  => $orderStatus,
            'subtotal_fils' => 10000,
            'vat_fils'      => 1000,
            'total_fils'    => 11000,
            'payment_method' => 'card',
        ]);

        $transaction = TapTransaction::create([
            'order_id'       => $order->id,
            'tap_charge_id'  => $tapChargeId,
            'amount_fils'    => 11000,
            'status'         => $txStatus,
            'attempt_number' => 1,
        ]);

        return [$order, $transaction];
    }

    public function test_result_endpoint_handles_captured_charge(): void
    {
        Event::fake([PaymentCaptured::class]);

        $user = User::factory()->create();
        [$order, $tx] = $this->makeOrderWithTransaction($user);

        $mock = $this->mock(TapApiService::class);
        $mock->shouldReceive('retrieveCharge')->once()->andReturn([
            'id'       => 'chg_test_123',
            'status'   => 'CAPTURED',
            'response' => ['code' => '000', 'message' => 'Captured'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/payments/result?tap_id=chg_test_123');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'captured');

        $this->assertDatabaseHas('tap_transactions', [
            'tap_charge_id' => 'chg_test_123',
            'status'        => 'captured',
        ]);

        Event::assertDispatched(PaymentCaptured::class);
    }

    public function test_result_endpoint_handles_failed_charge(): void
    {
        Event::fake([PaymentFailed::class]);

        $user = User::factory()->create();
        [$order, $tx] = $this->makeOrderWithTransaction($user);

        $mock = $this->mock(TapApiService::class);
        $mock->shouldReceive('retrieveCharge')->once()->andReturn([
            'id'       => 'chg_test_123',
            'status'   => 'FAILED',
            'response' => ['code' => '100', 'message' => 'Card declined'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/payments/result?tap_id=chg_test_123');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'failed');

        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_idempotent_handler_skips_already_captured(): void
    {
        $user = User::factory()->create();
        [$order, $tx] = $this->makeOrderWithTransaction($user, 'chg_test_123', 'paid', 'captured');

        $mock = $this->mock(TapApiService::class);
        $mock->shouldNotReceive('retrieveCharge');

        $response = $this->actingAs($user)->getJson('/api/v1/payments/result?tap_id=chg_test_123');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'captured');
    }

    public function test_cannot_view_another_users_payment_result(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$order, $tx] = $this->makeOrderWithTransaction($owner);

        $mock = $this->mock(TapApiService::class);
        $mock->shouldReceive('retrieveCharge')->once()->andReturn([
            'id'     => 'chg_test_123',
            'status' => 'CAPTURED',
        ]);

        $response = $this->actingAs($other)->getJson('/api/v1/payments/result?tap_id=chg_test_123');

        $response->assertStatus(403);
    }

    public function test_missing_tap_id_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/payments/result');

        $response->assertStatus(422);
    }

    public function test_unknown_tap_id_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/payments/result?tap_id=chg_nonexistent');

        $response->assertStatus(422);
    }
}
