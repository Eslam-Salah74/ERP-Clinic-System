<?php

namespace Modules\Inventory\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'stock_unit' => $this->stock_unit,
            'conversion_factor' => $this->conversion_factor,
            'unit' => $this->unit,
            'selling_price' => $this->selling_price,
            'current_stock' => $this->current_stock,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
