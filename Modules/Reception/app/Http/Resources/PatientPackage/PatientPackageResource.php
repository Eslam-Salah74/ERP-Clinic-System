<?php

namespace Modules\Reception\Http\Resources\PatientPackage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->name,
            'patient_phone' => $this->patient?->phone,
            'package_id' => $this->package_id,
            'package_name' => $this->package?->name,
            'package_type' => $this->package?->type?->value ?? $this->package?->type,
            'department_id' => $this->package?->department_id,
            'department_name' => $this->package?->department?->name,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'total_price' => (float) $this->total_price,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'is_fully_paid' => (float) $this->remaining_amount <= 0,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label() ?? null,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'is_expired' => $this->expires_at ? $this->expires_at->isPast() : false,
            'notes' => $this->notes,
            'created_by' => $this->creator?->name,
            'balances' => PatientPackageBalanceResource::collection($this->whenLoaded('balances')),
            'consumptions' => PatientPackageConsumptionResource::collection($this->whenLoaded('consumptions')),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
