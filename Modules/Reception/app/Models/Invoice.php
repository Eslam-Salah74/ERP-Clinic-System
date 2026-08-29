<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Filters\Invoice\InvoiceFilter;
use Modules\Reception\Models\InvoiceItem;
use Modules\Reception\Models\Transaction;



class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';
    protected $guarded = ['id'];
protected $fillable = [
        'invoice_number', 'patient_id', 'appointment_id', 'doctor_id', 'nurse_id',
        'shift_id', 'queue_number', 'type', 'status', 'payment_method',
        'sub_total', 'discount', 'grand_total', 'refunded_amount', 'notes', 'created_by'
    ];

    protected $casts = [
        'type' => InvoiceTypeEnum::class,
        'status' => InvoiceStatusEnum::class,
        'payment_method' => PaymentMethodEnum::class,
    ];

    // العلاقات
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter($query, InvoiceFilter $filter)
    {
        return $filter->apply($query);
    }
}
