<?php

namespace Modules\Reception\Filters\Invoice;

use App\Filters\Filters;

class InvoiceFilter extends Filters
{
    protected $var_filters = [
        'search',
        'invoice_number',
        'status',
        'type',
        'doctor_id',
        'patient_id',
    ];

    public function search($search)
    {
        return $this->builder->where(function ($query) use ($search) {
            $query->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
        });
    }

    public function invoiceNumber($invoiceNumber)
    {
        return $this->builder->where('invoice_number', 'like', "%{$invoiceNumber}%");
    }
}
