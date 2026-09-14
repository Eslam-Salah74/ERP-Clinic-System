<?php

namespace Modules\HR\Database\Seeders\Expense;

use Illuminate\Database\Seeder;

class ExpensePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'expenses' => 'HR',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
