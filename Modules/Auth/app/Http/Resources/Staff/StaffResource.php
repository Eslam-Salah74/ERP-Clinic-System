<?php

namespace Modules\Auth\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'department_id' => $this->department_id,
            'role_id' => $this->role_id,
            'role' => $this->whenLoaded('role'),
            'basic_salary' => $this->basic_salary,
            // 'allowances' => $this->allowances,
            'is_active' => $this->is_active,
            'achieved_target' => $this->achieved_target,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
