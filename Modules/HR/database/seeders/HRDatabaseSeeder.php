<?php

namespace Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HR\Database\Seeders\Expense\ExpensePermissionDatabaseSeeder;
use Modules\HR\Database\Seeders\Expense\ExpenseDatabaseSeeder;

class HRDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(ExpensePermissionDatabaseSeeder::class);
        $this->call(ExpenseDatabaseSeeder::class);
    }
}
