<?php

namespace Modules\HR\Http\Resources\Expense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'shift_id' => $this->shift_id,
            'shift' => $this->whenLoaded('shift'),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
