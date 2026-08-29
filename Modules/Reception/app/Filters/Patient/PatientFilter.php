<?php

namespace Modules\Reception\Filters\Patient;

use App\Filters\Filters;

class PatientFilter extends Filters
{
    protected $var_filters = [
        'name',
        'phone',
        'gender',
        'is_staff',
        'created_by',
        'search',
    ];

    

    public function search($search)
    {
        return $this->builder->where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
        });
    }
}
