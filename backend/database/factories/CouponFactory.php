<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Cart\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('????##')),
            'name' => [
                'ar' => $this->faker->word(),
                'en' => $this->faker->word(),
            ],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order_amount_fils' => 0,
            'max_uses_per_user' => 1,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'applicable_to' => 'all_products',
        ];
    }

    public function percentage(): static
    {
        return $this->state(['discount_type' => 'percentage', 'discount_value' => 15]);
    }

    public function fixed(): static
    {
        return $this->state(['discount_type' => 'fixed_fils', 'discount_value' => 5000]);
    }
}
