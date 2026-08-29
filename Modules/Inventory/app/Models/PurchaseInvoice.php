<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Inventory\Filters\PurchaseInvoice\PurchaseInvoiceFilter;
use Modules\Inventory\Models\PurchaseInvoiceItem;
use Modules\Inventory\Models\Supplier;

class PurchaseInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_invoices';
    protected $guarded = ['id'];

    public function scopeFilter($query, PurchaseInvoiceFilter $filter)
    {
        return $filter->apply($query);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }


    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_invoice_id');
    }
}
