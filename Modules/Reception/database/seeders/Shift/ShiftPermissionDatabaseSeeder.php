<?php

namespace Modules\Reception\Database\Seeders\Shift;

use Illuminate\Database\Seeder;

class ShiftPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['manage'];
        $models = [
            'shifts' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
