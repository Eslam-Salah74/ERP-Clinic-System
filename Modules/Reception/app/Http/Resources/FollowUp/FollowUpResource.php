<?php

namespace Modules\Reception\Http\Resources\FollowUp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient'),
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor'),
            'appointment_id' => $this->appointment_id,
            'appointment' => $this->whenLoaded('appointment'),
            'shift_id' => $this->shift_id,
            'shift' => $this->whenLoaded('shift'),
            'follow_up_date' => $this->follow_up_date?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
