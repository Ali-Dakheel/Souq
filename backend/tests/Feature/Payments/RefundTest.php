<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\RefundRequested;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private function makePaidOrder(User $user): array
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'order_status' => 'paid',
            'subtotal_fils' => 10000,
            'vat_fils' => 1000,
            'total_fils' => 11000,
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $transaction = TapTransaction::create([
            'order_id' => $order->id,
            'tap_charge_id' => 'chg_'.uniqid(),
            'amount_fils' => 11000,
            'status' => 'captured',
            'attempt_number' => 1,
        ]);

        return [$order, $transaction];
    }

    public function test_customer_can_request_refund(): void
    {
        Event::fake([RefundRequested::class]);

        $user = User::factory()->create();
        [$order, $tx] = $this->makePaidOrder($user);

        $response = $this->actingAs($user)->postJson("/api/v1/payments/{$tx->id}/refund", [
            'reason' => 'customer_request',
            'notes' => 'Changed my mind',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.refund_amount_fils', 11000);

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'tap_transaction_id' => $tx->id,
            'status' => 'pending',
            'refund_reason' => 'customer_request',
            'requested_by_user_id' => $user->id,
        ]);

        Event::assertDispatched(RefundRequested::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $user = User::factory()->create();
        [$order, $tx] = $this->makePaidOrder($user);

        $response = $this->postJson("/api/v1/payments/{$tx->id}/refund", [
            'reason' => 'customer_request',
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_refund_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$order, $tx] = $this->makePaidOrder($owner);

        $response = $this->actingAs($intruder)->postJson("/api/v1/payments/{$tx->id}/refund", [
            'reason' => 'customer_request',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_refund_uncaptured_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-2026-00001',
            'user_id' => $user->id,
            'order_status' => 'initiated',
            'subtotal_fils' => 10000,
            'vat_fils' => 1000,
            'total_fils' => 11000,
            'payment_method' => 'card',
        ]);

        $tx = TapTransaction::create([
            'order_id' => $order->id,
            'tap_charge_id' => 'chg_initiated',
            'amount_fils' => 11000,
            'status' => 'initiated',
            'attempt_number' => 1,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/payments/{$tx->id}/refund", [
            'reason' => 'customer_request',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_submit_duplicate_refund_request(): void
    {
        $user = User::factory()->create();
        [$order, $tx] = $this->makePaidOrder($user);

        // Existing pending refund
        Refund::create([
            'order_id' => $order->id,
            'tap_transaction_id' => $tx->id,
            'refund_amount_fils' => 11000,
            'refund_reason' => 'customer_request',
            'status' => 'pending',
            'requested_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/payments/{$tx->id}/refund", [
            'reason' => 'customer_request',
        ]);

        $response->assertStatus(422);
    }

    public function test_reason_is_required(): void
    {
        $user = User::factory()->create();
        [$order, $tx] = $this->makePaidOrder($user);

        $response = $this->actingAs($user)->postJson("/api/v1/payments/{$tx->id}/refund", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }

    public function test_invalid_reason_is_rejected(): void
    {
        $user = User::factory()->create();
        [$order, $tx] = $this->makePaidOrder($user);

        $response = $this->actingAs($user)->postJson("/api/v1/payments/{$tx->id}/refund", [
            'reason' => 'invalid_reason',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }
}
