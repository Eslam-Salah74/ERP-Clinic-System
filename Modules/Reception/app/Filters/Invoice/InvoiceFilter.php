<?php

namespace Modules\Reception\Filters\Invoice;

use App\Filters\Filters;

class InvoiceFilter extends Filters
{
    protected $var_filters = [
        'search',
    ];

    public function search($search)
    {
        return $this->builder->whereHas('patient', function ($query) use ($search) {
            $query->where('name', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
        });
    }
}
