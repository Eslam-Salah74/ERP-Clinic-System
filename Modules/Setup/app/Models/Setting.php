<?php

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Setup\Filters\Setting\SettingFilter;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'settings';
    protected $guarded = ['id'];
    protected $fillable = ['key', 'value', 'display_name', 'type'];

    public function scopeFilter($query, SettingFilter $filter)
    {
        return $filter->apply($query);
    }
}
