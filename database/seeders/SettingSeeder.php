<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_key' => 'low_stock_threshold', 'setting_value' => '1'],
            ['setting_key' => 'enable_item_discount', 'setting_value' => 'true'],
            ['setting_key' => 'enable_transaction_discount', 'setting_value' => 'true'],
            ['setting_key' => 'qris_image_path', 'setting_value' => null],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['setting_key' => $setting['setting_key']],
                [
                    'id' => (string) Str::uuid(),
                    'setting_value' => $setting['setting_value'],
                ]
            );
        }
    }
}
