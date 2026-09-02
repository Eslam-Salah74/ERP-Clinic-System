<?php

namespace App\Traits;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait PermissionSeederTrait
{
    public function createOrUpdatePermissions(array $models, array $actions): void
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

        // ✅ تحديث تلقائي للـ Super Admin بعد كل Permission Seeder
        // بالشكل ده أي سيدر جديد بيضيف صلاحيات، السوبر أدمن بياخدها تلقائياً
        $this->syncSuperAdminPermissions();
    }

    /**
     * منح Super Admin كل الصلاحيات الموجودة في الداتابيز.
     * يتم استدعاؤها تلقائياً بعد كل Permission Seeder.
     */
    private function syncSuperAdminPermissions(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')
            ->where('guard_name', 'api')
            ->first();

        if ($superAdminRole) {
            // تنظيف الكاش مرة أخرى قبل جلب كل الصلاحيات
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $superAdminRole->syncPermissions(Permission::where('guard_name', 'api')->get());
        }
    }
}
