<?php

namespace Modules\Reception\Database\Seeders\Patient;

use Illuminate\Database\Seeder;

class PatientPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'patients' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}