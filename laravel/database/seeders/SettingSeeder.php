<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'trial_days' => '14', 'vat_rate' => '15', 'currency' => 'SAR', 'site_name' => 'BuildXact Saudi',
            'support_email' => 'support@buildxact-saudi.local', 'support_phone' => '+966 11 234 5678',
            'bank_name' => '', 'bank_account_name' => '', 'bank_iban' => '', 'bank_account_number' => '',
            'bank_transfer_enabled' => '1',
            'moyasar_publishable_key' => '', 'moyasar_secret_key' => '', 'moyasar_enabled' => '0',
            'platform_legal_name_en' => 'BuildXact Saudi', 'platform_legal_name_ar' => '',
            'platform_vat_number' => '', 'platform_cr_number' => '',
            'platform_building_number' => '', 'platform_street_name' => '', 'platform_district' => '',
            'platform_city' => 'Riyadh', 'platform_postal_code' => '', 'platform_additional_number' => '',
            'platform_logo_path' => '', 'platform_cr_document_path' => '', 'platform_vat_document_path' => '',
            'whatsapp_enabled' => '0', 'whatsapp_phone_number_id' => '', 'whatsapp_access_token' => '',
            'smtp_enabled' => '0', 'smtp_host' => '', 'smtp_port' => '587', 'smtp_encryption' => 'tls',
            'smtp_username' => '', 'smtp_password' => '', 'smtp_from_email' => '', 'smtp_from_name' => 'BuildXact Saudi',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->command?->info('Platform settings seeded.');
    }
}
