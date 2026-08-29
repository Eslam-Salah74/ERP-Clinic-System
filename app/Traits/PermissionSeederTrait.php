<?php

namespace App\Traits;

use Spatie\Permission\Models\Permission;

trait PermissionSeederTrait
{
    public function createOrUpdatePermissions(array $models, array $actions)
    {
        // تنظيف الكاش الخاص بمكتبة Spatie لتجنب أي تعارض
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($models as $model => $moduleName) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['name' => "{$action} {$model}", 'guard_name' => 'api']
                );
            }
        }
    }
}
