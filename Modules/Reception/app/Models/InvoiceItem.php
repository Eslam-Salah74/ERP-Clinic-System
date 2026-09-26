<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Models\Item;
use Modules\Setup\Models\Service;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'item_type', 'service_id', 'product_id', 'service_items_ids',
        'item_name', 'unit_price', 'quantity', 'total_price', 'returned_qty'
    ];

    protected $casts = [
        'service_items_ids' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function getServiceItemsAttribute()
    {
        $ids = $this->service_items_ids;
        if (empty($ids) || !is_array($ids)) {
            return collect();
        }

        if ($this->relationLoaded('service') && $this->service && $this->service->relationLoaded('items')) {
            $matchingItems = $this->service->items->filter(function ($item) use ($ids) {
                return in_array($item->id, $ids) || (isset($item->pivot->id) && in_array($item->pivot->id, $ids));
            })->values();
            if ($matchingItems->isNotEmpty()) {
                return $matchingItems;
            }
        }

        if ($this->service_id) {
            $service = $this->relationLoaded('service') ? $this->service : Service::with('items')->find($this->service_id);
            if ($service && $service->relationLoaded('items')) {
                $matchingItems = $service->items->filter(function ($item) use ($ids) {
                    return in_array($item->id, $ids) || (isset($item->pivot->id) && in_array($item->pivot->id, $ids));
                })->values();
                if ($matchingItems->isNotEmpty()) {
                    return $matchingItems;
                }
            }
        }

        return Item::whereIn('id', $ids)->get();
    }
}
