<?php

namespace Modules\Reception\Http\Resources\PatientPackage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientPackageBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_package_id' => $this->patient_package_id,
            'item_type' => $this->item_type?->value ?? $this->item_type,
            'item_type_label' => $this->item_type?->label() ?? null,
            'service_id' => $this->service_id,
            'service_name' => $this->service?->name,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'custom_name' => $this->custom_name,
            'display_name' => $this->custom_name ?? ($this->service?->name ?? ($this->product?->name ?? 'بند باقة')),
            'total_quantity' => (float) $this->total_quantity,
            'consumed_quantity' => (float) $this->consumed_quantity,
            'remaining_quantity' => (float) $this->remaining_quantity,
            'unit' => $this->unit,
            'is_exhausted' => (float) $this->remaining_quantity <= 0,
        ];
    }
}
