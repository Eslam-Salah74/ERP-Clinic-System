<?php

namespace Modules\HR\Http\Resources\CommissionTemplate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'items_count' => $this->items_count ?? $this->items?->count() ?? 0,
            'items' => CommissionTemplateItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

