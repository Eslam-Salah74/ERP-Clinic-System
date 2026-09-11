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
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'api']
        );

        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);

        $adminUser = User::firstOrCreate(
            ['phone' => '01110731636'],
            [
                'name' => 'Eslam Salah',
                'email' => 'admin@clinic.com',
                'password' => Hash::make('257411'),
                'type' => 'admin',
                'department_id' => 1,
                'role_id' => $superAdminRole->id,
                'basic_salary' => 0,
                'allowances' => 0,
                'is_active' => true,
                'achieved_target' => true,
            ]
        );

        if (!$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole($superAdminRole);
        }
    }
}
