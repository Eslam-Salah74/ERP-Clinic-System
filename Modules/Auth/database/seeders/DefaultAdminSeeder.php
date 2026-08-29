<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء رول السوبر أدمن (لو مش موجود)
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'api']
        );

        // 2. جلب كل الصلاحيات اللي اتكريتت في الداتابيز وإعطائها للسوبر أدمن
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);

        // 3. إنشاء حساب المدير العام (Eslam Salah) وربطه بالرول
        $adminUser = User::firstOrCreate(
            ['phone' => '01110731636'], // رقم الهاتف اللي هتعمل بيه Login
            [
                'name' => 'Eslam Salah',
                'email' => 'admin@clinic.com',
                'password' => Hash::make('257411'), // الباسورد
                'type' => 'admin',
                'department_id' => 1,
                'role_id' => $superAdminRole->id,
                'basic_salary' => 0,
                'allowances' => 0,
                'is_active' => true,
                'achieved_target' => true,
            ]
        );

        // 4. تأكيد ربط اليوزر بالرول تبع مكتبة Spatie
        if (!$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole($superAdminRole);
        }
    }
}
