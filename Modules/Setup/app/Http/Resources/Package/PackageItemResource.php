<?php

namespace Modules\Setup\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_id' => $this->package_id,
            'item_type' => $this->item_type?->value ?? $this->item_type,
            'item_type_label' => $this->item_type?->label() ?? null,
            'service_id' => $this->service_id,
            'service_name' => $this->service?->name,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'custom_name' => $this->custom_name,
        ];
    }
}
