<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TapTransaction>
 */
class TapTransactionFactory extends Factory
{
    protected $model = TapTransaction::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'attempt_number' => 1,
            'tap_charge_id' => 'chg_'.Str::random(20),
            'amount_fils' => 50000,
            'currency' => 'BHD',
            'status' => 'initiated',
            'payment_method' => $this->faker->randomElement(['benefit', 'benefit_pay_qr', 'card', 'apple_pay']),
            'tap_response' => [],
        ];
    }

    public function captured(): static
    {
        return $this->state(['status' => 'captured']);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed']);
    }
}
