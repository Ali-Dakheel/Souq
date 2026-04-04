<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Promotions\Models\PromotionAction;
use App\Modules\Promotions\Models\PromotionCondition;
use App\Modules\Promotions\Models\PromotionRule;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        // --- Rule 1: Summer Sale ---
        // Condition: cart_total >= 5000 fils (5 BHD minimum)
        // Action: 10% off cart
        $rule1 = PromotionRule::create([
            'name_en' => 'Summer Sale',
            'name_ar' => 'تخفيضات الصيف',
            'is_active' => true,
            'priority' => 10,
            'is_exclusive' => false,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule1->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 5000,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule1->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 10],
        ]);

        // --- Rule 2: Free Shipping on Orders 10 BHD+ ---
        // Condition: cart_total >= 10000 fils (10 BHD minimum)
        // Action: free_shipping (no discount, just flag)
        $rule2 = PromotionRule::create([
            'name_en' => 'Free Shipping on Orders 10 BHD+',
            'name_ar' => 'شحن مجاني للطلبات فوق 10 دنانير',
            'is_active' => true,
            'priority' => 20,
            'is_exclusive' => false,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule2->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 10000,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule2->id,
            'type' => 'free_shipping',
            'value' => [],
        ]);

        // --- Rule 3: VIP Exclusive Deal ---
        // Condition: cart_total >= 20000 fils (20 BHD minimum)
        // Action: 20% off cart
        // Exclusive: true (stops further rules)
        // Max uses: 100 global
        $rule3 = PromotionRule::create([
            'name_en' => 'VIP Exclusive Deal',
            'name_ar' => 'عرض VIP حصري',
            'is_active' => true,
            'priority' => 5,
            'is_exclusive' => true,
            'max_uses_global' => 100,
        ]);

        PromotionCondition::create([
            'promotion_rule_id' => $rule3->id,
            'type' => 'cart_total',
            'operator' => 'gte',
            'value' => 20000,
        ]);

        PromotionAction::create([
            'promotion_rule_id' => $rule3->id,
            'type' => 'percent_off_cart',
            'value' => ['percent' => 20],
        ]);
    }
}
