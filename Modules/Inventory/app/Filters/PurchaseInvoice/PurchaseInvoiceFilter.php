<?php

namespace Modules\Inventory\Filters\PurchaseInvoice;

use App\Filters\Filters;

class PurchaseInvoiceFilter extends Filters
{
    protected $var_filters = [
        'supplier_id',
        'total_amount',
        
    ];
}
