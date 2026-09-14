<?php

namespace Modules\Reception\Database\Seeders\FollowUp;

use Illuminate\Database\Seeder;

class FollowUpPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'follow_ups' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}
