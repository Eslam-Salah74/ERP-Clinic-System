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
                return $this->permissions->pluck('name');
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
