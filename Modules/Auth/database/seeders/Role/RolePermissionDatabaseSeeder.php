<?php

namespace Modules\Auth\Database\Seeders\Role;

use Illuminate\Database\Seeder;

class RolePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'roles' => 'Auth',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}