<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Jobs\ProcessTapWebhookJob;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_returns_200_and_dispatches_job(): void
    {
        Queue::fake([ProcessTapWebhookJob::class]);

        $user = User::factory()->create();
        $order = Order::create([
            'order_number'  => 'ORD-2026-00001',
            'user_id'       => $user->id,
            'order_status'  => 'initiated',
            'subtotal_fils' => 10000,
            'vat_fils'      => 1000,
            'total_fils'    => 11000,
            'payment_method' => 'card',
        ]);

        TapTransaction::create([
            'order_id'       => $order->id,
            'tap_charge_id'  => 'chg_webhook_test',
            'amount_fils'    => 11000,
            'status'         => 'initiated',
            'attempt_number' => 1,
        ]);

        $response = $this->postJson('/api/v1/webhooks/tap', [
            'id'     => 'chg_webhook_test',
            'status' => 'CAPTURED',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');

        Queue::assertPushed(ProcessTapWebhookJob::class, function ($job) {
            return $job->tapChargeId === 'chg_webhook_test';
        });
    }

    public function test_webhook_without_charge_id_returns_400(): void
    {
        $response = $this->postJson('/api/v1/webhooks/tap', [
            'status' => 'CAPTURED',
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_with_invalid_signature_returns_401(): void
    {
        // Set a webhook secret
        config(['services.tap.webhook_secret' => 'test_secret_key']);

        $response = $this->postJson('/api/v1/webhooks/tap', [
            'id'     => 'chg_test',
            'status' => 'CAPTURED',
        ], [
            'hashstring' => 'invalid_hash',
        ]);

        $response->assertStatus(401);
    }
}
