<?php

namespace Modules\Inventory\Filters\Item;

use App\Filters\Filters;

class ItemFilter extends Filters
{
    protected $var_filters = [
        'name',
        'is_active',
        'search', // أضفنا متغير البحث هنا
    ];

    /**
     * دالة للبحث الشامل (بالاسم)
     */
    protected function search($value)
    {
        return $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%");
        });
    }
}
