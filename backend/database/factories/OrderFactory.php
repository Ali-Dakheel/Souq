<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.str_pad((string) $this->faker->unique()->randomNumber(5), 5, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'order_status' => 'pending',
            'subtotal_fils' => 50000,
            'coupon_discount_fils' => 0,
            'vat_fils' => 5000,
            'total_fils' => 55000,
            'payment_method' => $this->faker->randomElement(['benefit', 'benefit_pay_qr', 'card', 'apple_pay']),
            'locale' => 'ar',
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'order_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(['order_status' => 'pending']);
    }
}
