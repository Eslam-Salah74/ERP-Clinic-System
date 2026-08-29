<?php

namespace Modules\Setup\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Setup\Http\Resources\Department\DepartmentResource;
use Modules\Inventory\Http\Resources\Item\ItemResource; // استدعاء ريسورس المخزن

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type, // إرجاع نوع الخدمة (consultation, session, device)
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),

            // إرجاع المنتجات المرتبطة بالجلسة (لو موجودة) مع كمياتها في الـ pivot
            'items' => ItemResource::collection($this->whenLoaded('items')),

            'price' => $this->price,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
