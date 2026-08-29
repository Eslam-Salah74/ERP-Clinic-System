<?php

namespace Modules\Reception\Http\Resources\Appointment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reception\Http\Resources\Patient\PatientResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->doctor?->name,
            'service_id' => $this->service_id,
            'service_name' => $this->service?->name,
            'appointment_date' => $this->appointment_date?->toIso8601String(),
            'visit_type' => $this->visit_type->value ?? $this->visit_type,
            'status' => $this->status->value ?? $this->status,
            'shift_id' => $this->shift_id,
            'shift' => $this->whenLoaded('shift'),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
