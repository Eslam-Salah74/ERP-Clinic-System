<?php

namespace Modules\Setup\Database\Seeders\Setting;

use Illuminate\Database\Seeder;
use Modules\Setup\Models\Setting;

class SettingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
       $settings = [
            [
                'key' => 'clinic_name',
                'value' => 'عيادة الجولف',
                'display_name' => 'اسم العيادة',
                'type' => 'text'
            ],
            [
                'key' => 'follow_up_max_days',
                'value' => '30', // لو اتأخرت أكثر من شهر بيتحسب كشف من أول وجديد
                'display_name' => 'أقصى مدة للمتابعة (بالأيام)',
                'type' => 'number'
            ],
            [
                'key' => 'staff_discount_percentage',
                'value' => '20',
                'display_name' => 'نسبة خصم الموظفين من الخدمات (%)',
                'type' => 'number'
            ],
            ['key' => 'enable_gps_attendance', 'value' => 'true'], // تفعيل أو إيقاف نظام البصمة الجغرافية (True/False)
            ['key' => 'clinic_latitude', 'value' => '30.044420'],   // خط عرض موقع العيادة الجغرافي
            ['key' => 'clinic_longitude', 'value' => '31.235712'],  // خط طول موقع العيادة الجغرافي
            ['key' => 'clinic_radius_meters', 'value' => '50'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
