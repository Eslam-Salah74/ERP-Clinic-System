<?php

namespace Modules\Inventory\Database\Seeders\Item;

use Illuminate\Database\Seeder;

class ItemPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'items' => 'Inventory',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}