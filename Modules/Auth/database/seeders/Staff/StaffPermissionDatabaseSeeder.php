<?php

namespace Modules\Auth\Database\Seeders\Staff;

use Illuminate\Database\Seeder;

class StaffPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'staff' => 'Auth',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
