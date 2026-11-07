<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Setup\Models\Department;

class CommissionTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'commission_templates';

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(CommissionTemplateItem::class, 'commission_template_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts()
    {
        return $this->hasMany(StaffContract::class, 'commission_template_id');
    }
}

