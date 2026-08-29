<?php

namespace Modules\Auth\Filters\Staff;

use App\Filters\Filters;

class StaffFilter extends Filters
{
    protected $var_filters = [
        'name',
        'phone',
        'type',
        'role_id',
        'search', // أضفنا متغير البحث هنا
    ];

    /**
     * دالة للبحث الشامل (بالاسم أو الهاتف أو البريد)
     */
    protected function search($value)
    {
        return $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%")
                  ->orWhere('phone', 'like', "%{$value}%")
                  ->orWhere('email', 'like', "%{$value}%");
        });
    }
}
