<?php

namespace Modules\HR\Database\Seeders\Contract;

use Illuminate\Database\Seeder;
use App\Traits\PermissionSeederTrait;

class ContractPermissionDatabaseSeeder extends Seeder
{
    use PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'staff_contracts' => 'HR',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
