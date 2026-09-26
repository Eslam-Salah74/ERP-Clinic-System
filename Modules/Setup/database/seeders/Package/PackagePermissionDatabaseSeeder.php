<?php

namespace Modules\Setup\Database\Seeders\Package;

use Illuminate\Database\Seeder;

class PackagePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'packages' => 'Setup',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
