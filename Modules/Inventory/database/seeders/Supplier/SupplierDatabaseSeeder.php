<?php

namespace Modules\Inventory\Database\Seeders\Supplier;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Supplier;

class SupplierDatabaseSeeder extends Seeder
{
    public function run(): void
    {
       $companies = [
            'Love',
            'Mg',
            'Rinova',
            'Fusion',
            'Apera',
            'Lc',
            'NJ',
            'Skinium',
            'Skinderma',
            'Seda derm',
            'Stylage'
        ];

        foreach ($companies as $company) {
            Supplier::firstOrCreate(
                ['name' => $company],
                [
                    'company_name' => $company,
                    'is_active' => true
                ]
            );
        }
    }
}
