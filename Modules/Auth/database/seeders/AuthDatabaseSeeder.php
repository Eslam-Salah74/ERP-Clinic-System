<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\Role\RoleDatabaseSeeder;
use Modules\Auth\Database\Seeders\Role\RolePermissionDatabaseSeeder;
use Modules\Auth\Database\Seeders\Staff\StaffDatabaseSeeder;
use Modules\Auth\Database\Seeders\Staff\StaffPermissionDatabaseSeeder;
use Modules\Auth\Database\Seeders\DefaultAdminSeeder;


class AuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolePermissionDatabaseSeeder::class);
        $this->call(StaffPermissionDatabaseSeeder::class);

        // 2. إنشاء الأدوار الأساسية (دكتور، محاسب، موظف استقبال)
        $this->call(RoleDatabaseSeeder::class);

        // 3. إنشاء السوبر أدمن (عشان يلاقي الصلاحيات اللي اتكريتت فوق فيسحبها كلها لنفسه)
        $this->call(DefaultAdminSeeder::class);

        // 4. أخيراً إنشاء الموظفين وربطهم بالأدوار بتاعتهم
        $this->call(StaffDatabaseSeeder::class);
        // $this->call([]);
    }
}
