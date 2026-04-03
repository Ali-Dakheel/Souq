<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        // --- Bahrain Zone (active) ---
        $bahrain = ShippingZone::firstOrCreate(
            ['name_en' => 'Bahrain'],
            [
                'name_ar' => 'البحرين',
                'countries' => ['BH'],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // Method 1: Standard Delivery — flat_rate, 1500 fils (1.500 BHD)
        ShippingMethod::firstOrCreate(
            ['shipping_zone_id' => $bahrain->id, 'carrier' => 'standard', 'type' => 'flat_rate'],
            [
                'name_en' => 'Standard Delivery',
                'name_ar' => 'التوصيل العادي',
                'rate_fils' => 1500,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // Method 2: Free Delivery above 20 BHD — free_threshold, free above 20000 fils, 1500 fils below
        ShippingMethod::firstOrCreate(
            ['shipping_zone_id' => $bahrain->id, 'carrier' => 'standard', 'type' => 'free_threshold'],
            [
                'name_en' => 'Free Delivery (orders above 20 BHD)',
                'name_ar' => 'توصيل مجاني (الطلبات فوق 20 دينار)',
                'rate_fils' => 1500,
                'free_threshold_fils' => 20000,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // --- Saudi Arabia Zone (inactive stub) ---
        ShippingZone::firstOrCreate(
            ['name_en' => 'Saudi Arabia'],
            [
                'name_ar' => 'المملكة العربية السعودية',
                'countries' => ['SA'],
                'is_active' => false,
                'sort_order' => 1,
            ]
        );

        // --- UAE Zone (inactive stub) ---
        ShippingZone::firstOrCreate(
            ['name_en' => 'United Arab Emirates'],
            [
                'name_ar' => 'الإمارات العربية المتحدة',
                'countries' => ['AE'],
                'is_active' => false,
                'sort_order' => 2,
            ]
        );
    }
}
