<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Auth\Filters\Staff\StaffFilter;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    // أضفنا HasRoles علشان لو احتجنا نستخدم دوال Spatie الجاهزة زي ()can$
    use HasFactory, Notifiable, HasRoles, SoftDeletes;
    protected $guard_name = 'api';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'type', 'role_id', 'basic_salary', 'allowances','is_active',       // أضفناه هنا
        'achieved_target', 'department_id'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'type' => UserType::class, // ربط الحقل بالـ Enum
        ];
    }

    // 1. علاقة المستخدم بالرول (لجلب بيانات الرول مباشرة)
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // --- 2. دوال JWT المطلوبة لتسجيل الدخول ---
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


    public function scopeFilter($query, StaffFilter $filter)
    {
        return $filter->apply($query);
    }
}
