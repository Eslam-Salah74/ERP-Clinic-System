<?php

namespace Modules\Reception\Database\Seeders\Appointment;

use Illuminate\Database\Seeder;

class AppointmentPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'appointments' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}