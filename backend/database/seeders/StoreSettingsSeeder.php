<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Settings\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Legal identity fields use firstOrCreate — never overwrite live production values on re-seed
        StoreSetting::firstOrCreate(['key' => 'cr_number'], ['value' => 'CR-00000000', 'group' => 'legal']);
        StoreSetting::firstOrCreate(['key' => 'vat_number'], ['value' => 'VAT-000000000', 'group' => 'legal']);

        $settings = [
            ['key' => 'company_name_en', 'value' => '', 'group' => 'legal'],
            ['key' => 'company_name_ar', 'value' => '', 'group' => 'legal'],
            ['key' => 'company_address_en', 'value' => '', 'group' => 'legal'],
            ['key' => 'company_address_ar', 'value' => '', 'group' => 'legal'],
            ['key' => 'logo_path', 'value' => null, 'group' => 'branding'],
            ['key' => 'favicon_path', 'value' => null, 'group' => 'branding'],
            ['key' => 'support_email', 'value' => '', 'group' => 'commerce'],
            ['key' => 'support_phone', 'value' => '', 'group' => 'commerce'],
        ];

        foreach ($settings as $setting) {
            StoreSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']],
            );
        }

        // Use firstOrCreate for the sequence counters — never reset them on re-seed
        StoreSetting::firstOrCreate(
            ['key' => 'last_invoice_sequence'],
            ['value' => '0', 'group' => 'commerce'],
        );
        StoreSetting::firstOrCreate(
            ['key' => 'last_rma_sequence'],
            ['value' => '0', 'group' => 'commerce'],
        );
    }
}
