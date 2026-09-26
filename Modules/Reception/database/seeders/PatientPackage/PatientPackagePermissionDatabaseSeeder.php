<?php

namespace Modules\Reception\Database\Seeders\PatientPackage;

use Illuminate\Database\Seeder;

class PatientPackagePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'patient_packages' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
