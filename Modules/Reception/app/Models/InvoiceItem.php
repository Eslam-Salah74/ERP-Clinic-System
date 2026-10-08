<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Models\Item;
use Modules\Setup\Models\Service;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'item_type', 'service_id', 'product_id', 'package_id',
        'patient_package_id', 'patient_package_balance_id', 'service_items_ids',
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

    public function package()
    {
        return $this->belongsTo(\Modules\Setup\Models\Package::class, 'package_id');
    }

    public function patientPackage()
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }

    public function patientPackageBalance()
    {
        return $this->belongsTo(PatientPackageBalance::class, 'patient_package_balance_id');
    }

    public function getServiceItemsAttribute()
    {
        $ids = $this->service_items_ids;
        if (empty($ids) || !is_array($ids)) {
            return collect();
        }

        $service = null;
        if ($this->relationLoaded('service') && $this->service && $this->service->relationLoaded('items')) {
            $service = $this->service;
        } elseif ($this->service_id) {
            $service = $this->relationLoaded('service') ? $this->service : Service::with('items')->find($this->service_id);
        }

        if ($service && $service->relationLoaded('items')) {
            $serviceItems = $service->items;
            $chosenIds = array_map('intval', (array) $ids);
            $servicePivotIds = $serviceItems->pluck('pivot.id')->filter()->map(fn($id) => (int)$id)->all();

            $pivotMatches = array_intersect($chosenIds, $servicePivotIds);
            if (!empty($pivotMatches)) {
                return $serviceItems->filter(function ($item) use ($chosenIds) {
                    return isset($item->pivot->id) && in_array((int) $item->pivot->id, $chosenIds);
                })->values();
            } else {
                return $serviceItems->filter(function ($item) use ($chosenIds) {
                    return in_array((int) $item->id, $chosenIds);
                })->values();
            }
        }

        return Item::whereIn('id', $ids)->get();
    }
}
