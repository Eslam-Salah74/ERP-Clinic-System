<?php

namespace Modules\Reception\Http\Resources\PatientPackage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientPackageConsumptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_package_id' => $this->patient_package_id,
            'patient_package_balance_id' => $this->patient_package_balance_id,
            'item_name' => $this->balance?->custom_name ?? ($this->balance?->service?->name ?? ($this->balance?->product?->name ?? 'جلسة باقة')),
            'appointment_id' => $this->appointment_id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->doctor?->name,
            'nurse_id' => $this->nurse_id,
            'nurse_name' => $this->nurse?->name,
            'consumed_quantity' => (float) $this->consumed_quantity,
            'unit' => $this->unit,
            'notes' => $this->notes,
            'consumed_at' => $this->consumed_at?->format('Y-m-d H:i'),
        ];
    }
}
