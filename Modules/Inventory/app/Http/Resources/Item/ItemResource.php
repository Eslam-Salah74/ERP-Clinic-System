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
            'type' => $this->type, // إرجاع نوع الصنف (consumable أو retailable)
            'unit' => $this->unit, // إرجاع الوحدة (ml, piece, strip)
            'selling_price' => $this->selling_price,
            'current_stock' => $this->current_stock,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
