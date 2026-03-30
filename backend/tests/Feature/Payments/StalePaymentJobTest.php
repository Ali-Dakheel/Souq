<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Events\PaymentFailed;
use App\Modules\Payments\Jobs\CheckStalePaymentsJob;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Payments\Services\TapApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StalePaymentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_initiated_payment_gets_resolved(): void
    {
        Event::fake([PaymentCaptured::class]);

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

        // Travel back in time so the record's auto-managed created_at is 35 min ago
        Carbon::setTestNow(now()->subMinutes(35));

        $tx = TapTransaction::create([
            'order_id'       => $order->id,
            'tap_charge_id'  => 'chg_stale',
            'amount_fils'    => 11000,
            'status'         => 'initiated',
            'attempt_number' => 1,
        ]);

        Carbon::setTestNow(); // Reset to real time

        $mock = $this->mock(TapApiService::class);
        $mock->shouldReceive('retrieveCharge')
            ->with('chg_stale')
            ->once()
            ->andReturn([
                'id'       => 'chg_stale',
                'status'   => 'CAPTURED',
                'response' => ['code' => '000', 'message' => 'Captured'],
            ]);

        $job = new CheckStalePaymentsJob();
        $job->handle(app(\App\Modules\Payments\Services\PaymentService::class));

        $this->assertDatabaseHas('tap_transactions', [
            'tap_charge_id' => 'chg_stale',
            'status'        => 'captured',
        ]);

        Event::assertDispatched(PaymentCaptured::class);
    }

    public function test_fresh_initiated_payment_is_skipped(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number'  => 'ORD-2026-00002',
            'user_id'       => $user->id,
            'order_status'  => 'initiated',
            'subtotal_fils' => 10000,
            'vat_fils'      => 1000,
            'total_fils'    => 11000,
            'payment_method' => 'card',
        ]);

        TapTransaction::create([
            'order_id'       => $order->id,
            'tap_charge_id'  => 'chg_fresh',
            'amount_fils'    => 11000,
            'status'         => 'initiated',
            'attempt_number' => 1,
            // created_at defaults to now() — NOT stale
        ]);

        $mock = $this->mock(TapApiService::class);
        $mock->shouldNotReceive('retrieveCharge');

        $job = new CheckStalePaymentsJob();
        $job->handle(app(\App\Modules\Payments\Services\PaymentService::class));

        $this->assertDatabaseHas('tap_transactions', [
            'tap_charge_id' => 'chg_fresh',
            'status'        => 'initiated', // unchanged
        ]);
    }
}
