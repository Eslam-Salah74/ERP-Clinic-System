<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    use HasFactory;

    protected $table = 'payroll_items';
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_addition' => 'boolean',
        'metadata' => 'array',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}
