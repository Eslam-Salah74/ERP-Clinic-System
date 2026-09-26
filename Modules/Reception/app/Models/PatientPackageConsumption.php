<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientPackageConsumption extends Model
{
    use HasFactory;

    protected $table = 'patient_package_consumptions';
    protected $guarded = ['id'];

    protected $fillable = [
        'patient_package_id',
        'patient_package_balance_id',
        'appointment_id',
        'invoice_id',
        'invoice_item_id',
        'doctor_id',
        'nurse_id',
        'consumed_quantity',
        'unit',
        'notes',
        'consumed_at',
        'created_by',
    ];

    protected $casts = [
        'consumed_quantity' => 'decimal:2',
        'consumed_at' => 'datetime',
    ];

    public function patientPackage()
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }

    public function balance()
    {
        return $this->belongsTo(PatientPackageBalance::class, 'patient_package_balance_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
