<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Setup\Models\Service;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'item_type', 'service_id', 'product_id',
        'item_name', 'unit_price', 'quantity', 'total_price', 'returned_qty'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    
}
