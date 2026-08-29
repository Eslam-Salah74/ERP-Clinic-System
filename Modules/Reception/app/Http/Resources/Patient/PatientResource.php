<?php

namespace Modules\Reception\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'age' => $this->age,
            'is_staff' => (bool) $this->is_staff,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name, // اسم الموظف اللي سجل المريض
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
