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
        'start_date',
        'end_date',
        'date_from',
        'date_to',
        'search',
    ];

    public function dateFrom($date)
    {
        return $this->builder->where('start_date', '>=', $date);
    }

    public function dateTo($date)
    {
        return $this->builder->where('end_date', '<=', $date);
    }

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
