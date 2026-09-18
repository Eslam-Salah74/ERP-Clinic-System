<?php

namespace Modules\HR\Filters\Payroll;

use App\Filters\Filters;

class PayrollFilter extends Filters
{
    protected $var_filters = [
        'user_id',
        'month',
        'status',
        'target_achieved',
        'search',
    ];

    public function search($search)
    {
        return $this->builder->where(function ($query) use ($search) {
            $query->where('notes', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
        });
    }

    public function targetAchieved($value)
    {
        return $this->builder->where('target_achieved', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
