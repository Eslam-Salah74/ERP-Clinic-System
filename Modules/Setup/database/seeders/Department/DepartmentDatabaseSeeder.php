<?php

namespace Modules\Setup\Database\Seeders\Department;

use Illuminate\Database\Seeder;
use Modules\Setup\Models\Department;

class DepartmentDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'جلدية وتجميل',
            'علاج طبيعي',
            'تغذية علاجية',
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate([
                'name' => $dept
            ]);
        }
    }
}
