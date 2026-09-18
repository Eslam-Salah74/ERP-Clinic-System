<?php

namespace Modules\HR\Database\Seeders\Payroll;

use Illuminate\Database\Seeder;
use App\Traits\PermissionSeederTrait;

class PayrollPermissionDatabaseSeeder extends Seeder
{
    use PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete', 'approve', 'pay'];
        $models = [
            'payrolls' => 'HR',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
