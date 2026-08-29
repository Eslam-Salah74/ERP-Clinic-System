<?php

namespace Modules\Reception\Http\Resources\Shift;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'status' => $this->status->value ?? $this->status,
            'initial_balance' => $this->initial_balance,
            'final_balance' => $this->final_balance,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'is_late' => (bool) $this->is_late,
            'late_minutes' => $this->late_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'overtime_approved' => (bool) $this->overtime_approved,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
