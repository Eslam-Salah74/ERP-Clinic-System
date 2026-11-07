<?php

namespace Modules\HR\Http\Resources\CommissionTemplate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionTemplateItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commission_template_id' => $this->commission_template_id,
            'service_id' => $this->service_id,
            'service_name' => $this->service?->name,
            'service_type' => $this->service?->type,
            'service_price' => (float) ($this->service?->price ?? 0),
            'doctor_service_price' => (float) ($this->doctor_service_price ?? 0),
            'is_laser' => (bool) $this->is_laser,
            'commission_type' => $this->commission_type instanceof \BackedEnum ? $this->commission_type->value : $this->commission_type,
            'commission_value' => (float) $this->commission_value,
            'target_commission_value' => $this->target_commission_value !== null ? (float) $this->target_commission_value : null,
        ];
    }
}

