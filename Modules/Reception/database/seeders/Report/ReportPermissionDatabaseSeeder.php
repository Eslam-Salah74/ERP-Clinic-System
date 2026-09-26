<?php

namespace Modules\Reception\Database\Seeders\Report;

use Illuminate\Database\Seeder;

class ReportPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'export'];
        $models = [
            'reports' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
