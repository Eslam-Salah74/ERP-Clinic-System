<?php

namespace Modules\HR\Filters\Expense;

use App\Filters\Filters;

class ExpenseFilter extends Filters
{
    protected $var_filters = [
        'category',
        'payment_method',
        'expense_date',
        'shift_id',
        'created_by',
        'date_from',
        'date_to',
        'search',
    ];

    public function search($search)
    {
        return $this->builder->where(function ($query) use ($search) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
        });
    }

    public function dateFrom($date)
    {
        return $this->builder->whereDate('expense_date', '>=', $date);
    }

    public function dateTo($date)
    {
        return $this->builder->whereDate('expense_date', '<=', $date);
    }
}
