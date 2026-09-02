<?php

namespace Modules\Auth\Http\Controllers\Api\Permission;

use App\Http\Controllers\Controller;
use App\Support\API;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:read roles', only: ['index']),
        ];
    }

    /**
     * إرجاع كل الصلاحيات الموجودة في السيستم مع ترجمتها العربية
     * مجمّعة حسب الموديول لسهولة العرض في الفرونت
     */
    public function index()
    {
        // تحميل ملف الترجمة مباشرة بدل الاعتماد على locale التطبيق
        $clinicLang             = require resource_path('lang/ar/clinic.php');
        $permissionsTranslations = $clinicLang['permissions'] ?? [];


        // جلب كل الصلاحيات من الداتابيز
        $permissions = Permission::where('guard_name', 'api')
            ->orderBy('name')
            ->get()
            ->map(function ($permission) use ($permissionsTranslations) {
                return [
                    'id'         => $permission->id,
                    'name'       => $permission->name,
                    'label'      => $permissionsTranslations[$permission->name] ?? $permission->name,
                    'group'      => $this->extractGroup($permission->name),
                    'action'     => $this->extractAction($permission->name),
                ];
            })
            ->groupBy('group')
            ->map(function ($items, $group) {
                return [
                    'group'       => $group,
                    'permissions' => $items->values(),
                ];
            })
            ->values();

        return API::newInstance()
            ->isOk('تم جلب الصلاحيات بنجاح')
            ->setData($permissions)
            ->build();
    }

    /**
     * استخراج اسم المجموعة من اسم الصلاحية
     * مثال: "read roles" => "roles"
     */
    private function extractGroup(string $permissionName): string
    {
        $parts = explode(' ', $permissionName, 2);
        return $parts[1] ?? $permissionName;
    }

    /**
     * استخراج الفعل من اسم الصلاحية
     * مثال: "read roles" => "read"
     */
    private function extractAction(string $permissionName): string
    {
        $parts = explode(' ', $permissionName, 2);
        return $parts[0] ?? $permissionName;
    }
}
