<?php

namespace Modules\Inventory\Filters\Supplier;

use App\Filters\Filters;

class SupplierFilter extends Filters
{
    protected $var_filters = [
        'name',
        'phone',
        'company_name',
        'is_active',
        'search', // أضفنا متغير البحث هنا
    ];

    /**
     * دالة للبحث الشامل (بالاسم أو الهاتف أو الشركة)
     */
    protected function search($value)
    {
        return $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%")
                   ->orWhere('phone', 'like', "%{$value}%")
                   ->orWhere('company_name', 'like', "%{$value}%");
        });
    }
}
