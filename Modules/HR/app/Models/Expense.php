<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Enums\ExpenseCategoryEnum;
use Modules\HR\Filters\Expense\ExpenseFilter;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Models\Shift;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'category' => ExpenseCategoryEnum::class,
        'payment_method' => PaymentMethodEnum::class,
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter($query, ExpenseFilter $filter)
    {
        return $filter->apply($query);
    }
}
