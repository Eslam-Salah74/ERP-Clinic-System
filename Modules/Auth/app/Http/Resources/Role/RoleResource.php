<?php

namespace Modules\Auth\Http\Resources\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // هيرجع الصلاحيات لو طلبناها مع الرول
            'permissions' => $this->whenLoaded('permissions', function () {
                // استخدام lang_path بدلاً من resource_path لتتوافق مع مكان مجلد الـ lang في لافيل الحديثة
                $path = lang_path('ar/clinic.php');
                $translations = [];

                // فحص هل الملف موجود أصلاً على السيرفر لتفادي أي Server Error
                if (file_exists($path)) {
                    $clinicLang = require $path;
                    $translations = $clinicLang['permissions'] ?? [];
                }

                return $this->permissions->map(function ($permission) use ($translations) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $translations[$permission->name] ?? $permission->name,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
