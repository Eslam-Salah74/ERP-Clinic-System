<?php

namespace Modules\Auth\Database\Seeders\Role;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role; // <-- الاستدعاء الصحيح لمكتبة Spatie

class RoleDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة بالأدوار (Roles) الأساسية في نظام العيادة
        $roles = [
            'Super Admin',
            'Doctor',
            'Receptionist',
            'Accountant'
        ];

        // إنشاء الأدوار في قاعدة البيانات (مع التأكد من عدم تكرارها)
        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'api' // مهم جداً عشان إحنا شغالين API
            ]);
        }
    }
}
