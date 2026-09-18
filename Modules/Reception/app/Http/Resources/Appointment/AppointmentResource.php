<?php

namespace Modules\Reception\Http\Resources\Appointment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Http\Resources\Item\ItemResource;
use Modules\Reception\Http\Resources\Patient\PatientResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'queue_number' => $this->queue_number,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn() => new PatientResource($this->patient)),
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->doctor?->name,
            'service_id' => $this->service_id,
            'service_name' => $this->service?->name,
            'service_price' => (float) ($this->service?->price ?? 0),
            'service_type' => $this->service?->type instanceof \BackedEnum ? $this->service->type->value : (string) ($this->service?->type ?? ''),
            'service' => $this->relationLoaded('service') && $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'price' => (float) $this->service->price,
                'type' => $this->service->type instanceof \BackedEnum ? $this->service->type->value : (string) $this->service->type,
            ] : null,
            // 'service_items_ids' => $this->service_items_ids ?? [],
            'service_items' => ItemResource::collection($this->service_items),
            'appointment_date' => $this->appointment_date?->toIso8601String(),
            'appointment_date_formatted' => $this->appointment_date ? \Carbon\Carbon::parse($this->appointment_date)->format('Y-m-d') : null,
            'appointment_time' => $this->appointment_date ? \Carbon\Carbon::parse($this->appointment_date)->format('H:i') : null,
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
