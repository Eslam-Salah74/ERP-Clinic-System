<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Filters\PurchaseInvoice\PurchaseInvoiceFilter;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'quantity',
        'purchase_price',
        'total_price',
    ];

    // علاقة الصنف بالفاتورة التابع لها
    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    // علاقة صنف الفاتورة بجدول الأصناف الأساسي (Items) عشان نعرف اسمه وسعره
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function scopeFilter($query, PurchaseInvoiceFilter $filter)
    {
        return $filter->apply($query);
    }
}
