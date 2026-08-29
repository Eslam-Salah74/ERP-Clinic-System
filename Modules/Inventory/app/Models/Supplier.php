<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Inventory\Filters\Supplier\SupplierFilter;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'suppliers';
    protected $guarded = ['id'];
    protected $fillable = ['name', 'company_name', 'phone', 'notes', 'is_active'];

    public function scopeFilter($query, SupplierFilter $filter)
    {
        return $filter->apply($query);
    }
}
