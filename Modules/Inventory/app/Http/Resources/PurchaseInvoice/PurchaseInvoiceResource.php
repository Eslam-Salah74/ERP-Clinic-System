<?php

namespace Modules\Inventory\Http\Resources\PurchaseInvoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Http\Resources\Supplier\SupplierResource;

class PurchaseInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($invoiceItem) {
                    return [
                        'id' => $invoiceItem->id,
                        'item_id' => $invoiceItem->item_id,
                        'item_name' => $invoiceItem->item?->name,
                        'quantity' => $invoiceItem->quantity,
                        'purchase_price' => $invoiceItem->purchase_price,
                        'total_price' => $invoiceItem->total_price,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
