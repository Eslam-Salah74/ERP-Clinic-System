<?php

namespace Modules\Setup\Database\Seeders\Department;

use Illuminate\Database\Seeder;

class DepartmentPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'departments' => 'Setup',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}