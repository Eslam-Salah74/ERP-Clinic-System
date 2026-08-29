<?php

namespace Modules\Reception\Database\Seeders\Patient;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Reception\Models\Patient;

class PatientDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::first();
        $userId = $adminUser ? $adminUser->id : null;

        $patients = [
            [
                'name' => 'محمد أحمد إبراهيم',
                'phone' => '01012345678',
                'gender' => 'male',
                'age' => 30,
                'is_staff' => false,
                'created_by' => $userId,
            ],
            [
                'name' => 'فاطمة علي محمود',
                'phone' => '01123456789',
                'gender' => 'female',
                'age' => 25,
                'is_staff' => false,
                'created_by' => $userId,
            ],
            [
                'name' => 'محمود حسن السيد',
                'phone' => '01234567890',
                'gender' => 'male',
                'age' => 35,
                'is_staff' => true, // موظف بالعيادة لتجربة خصم الـ 20%
                'created_by' => $userId,
            ],
        ];

        foreach ($patients as $patient) {
            Patient::firstOrCreate(
                ['phone' => $patient['phone']], // منع التكرار برقم التليفون
                $patient
            );
        }
    }
}
