<?php

namespace Modules\Setup\Database\Seeders\Service;

use Illuminate\Database\Seeder;

class ServicePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'services' => 'Setup',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}