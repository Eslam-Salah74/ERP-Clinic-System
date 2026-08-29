<?php

namespace Modules\Auth\Database\Seeders\Staff;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class StaffDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. التأكد من وجود الأدوار (Roles) الأساسية
        $doctorRole       = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'api']);
        $receptionistRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => 'api']);
        $accountantRole   = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'api']);

        // 2. طاقم العمل الفعلي بناءً على ملفات البيزنس (دكتورة آية وباقي الفريق)
        $staffMembers = [
            [
                'name' => 'Dr. Aya', // دكتورة آية
                'phone' => '01011111111', // استبدل برقم الهاتف الحقيقي لو موجود في الملف
                'email' => 'dr.aya@clinic.com',
                'password' => Hash::make('12345678'), // باسورد افتراضي ويمكن تغييره
                'type' => 'doctor',
                'department_id' => 1, // استبدل بقيمة ID القسم المناسب
                'role_id' => $doctorRole->id,
                'role_name' => 'Doctor',
                'basic_salary' => 10000.00, // المرتب الأساسي المقترح
                'allowances' => 1500.00,    // البدلات
                'is_active' => true,
                'achieved_target' => true, // يمكن تعديلها حسب الأداء
            ],
            // يمكنك إضافة باقي الأطباء أو الموظفين هنا بنفس الهيكل:
            /*
            [
                'name' => 'اسم الموظف أو الدكتور',
                'phone' => '010xxxxxxxx',
                'email' => 'email@clinic.com',
                'password' => Hash::make('12345678'),
                'type' => 'receptionist', // أو accountant أو doctor
                'department_id' => 1, // استبدل بقيمة ID القسم المناسب  
                'role_id' => $receptionistRole->id,
                'role_name' => 'Receptionist',
                'basic_salary' => 5000.00,
                'allowances' => 500.00,
                'is_active' => true,
                'achieved_target' => false, // حسب الأداء الفعلي
            ],
            */
        ];

        // 3. إدخال البيانات وربطها بالصلاحيات (Spatie Roles)
        foreach ($staffMembers as $staffData) {
            $roleName = $staffData['role_name'];
            unset($staffData['role_name']); // إزالة اسم الرول مؤقتاً لعدم وجوده كعمود في الجدول

            $user = User::firstOrCreate(
                ['phone' => $staffData['phone']], // الشرط لمنع التكرار عند عمل Seed أكثر من مرة
                $staffData
            );

            // ربط المستخدم بالدور الخاص به في Spatie
            if (!$user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }
    }
}
