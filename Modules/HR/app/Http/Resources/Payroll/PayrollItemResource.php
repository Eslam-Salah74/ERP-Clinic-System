<?php

namespace Modules\HR\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'is_addition' => (bool) $this->is_addition,
            'reference_id' => $this->reference_id,
            'reference_type' => $this->reference_type,
            'metadata' => $this->metadata,
        ];
    }
}
