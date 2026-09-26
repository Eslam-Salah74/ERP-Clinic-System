<?php

namespace Modules\Setup\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'name' => $this->name,
            'type' => $this->type?->value ?? $this->type,
            'type_label' => $this->type?->label() ?? null,
            'original_price' => (float) $this->original_price,
            'price' => (float) $this->price,
            'discount_amount' => max(0, (float) $this->original_price - (float) $this->price),
            'discount_percentage' => (float) $this->original_price > 0 
                ? round(((float) $this->original_price - (float) $this->price) / (float) $this->original_price * 100, 2)
                : 0,
            'validity_days' => $this->validity_days,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'items' => PackageItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
