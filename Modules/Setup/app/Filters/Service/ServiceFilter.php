<?php

namespace Modules\Setup\Filters\Service;

use App\Filters\Filters;

class ServiceFilter extends Filters
{
    protected $var_filters = [
        'name',
        'department_id',
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
