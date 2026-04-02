<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tap_transaction_id' => TapTransaction::factory(),
            'refund_amount_fils' => 10000,
            'refund_reason' => 'customer_request',
            'status' => 'pending',
            'requested_by_user_id' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'processed_at' => now(),
            'processed_by_user_id' => User::factory(),
        ]);
    }
}
