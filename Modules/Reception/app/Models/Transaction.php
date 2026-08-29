<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Enums\TransactionTypeEnum;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_number', 'invoice_id', 'shift_id', 'type',
        'payment_method', 'amount', 'description', 'created_by'
    ];

    protected $casts = [
        'type' => TransactionTypeEnum::class,
        'payment_method' => PaymentMethodEnum::class,
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
