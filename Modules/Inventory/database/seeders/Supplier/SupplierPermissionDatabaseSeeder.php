<?php

namespace Modules\Inventory\Database\Seeders\Supplier;

use Illuminate\Database\Seeder;

class SupplierPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'suppliers' => 'Inventory',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}