<?php

namespace Database\Seeders;

use App\Modules\Customers\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        CustomerGroup::firstOrCreate(
            ['slug' => 'general'],
            ['name_en' => 'General', 'name_ar' => 'عام', 'is_default' => true]
        );
    }
}
