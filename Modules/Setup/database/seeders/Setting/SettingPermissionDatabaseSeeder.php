<?php

namespace Modules\Setup\Database\Seeders\Setting;

use Illuminate\Database\Seeder;

class SettingPermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'settings' => 'Setup',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}