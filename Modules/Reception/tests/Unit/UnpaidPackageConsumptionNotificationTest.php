<?php

namespace Modules\Reception\Tests\Unit;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Reception\Enums\PatientPackageStatusEnum;
use Modules\Reception\Models\Patient;
use Modules\Reception\Models\PatientPackage;
use Modules\Reception\Models\PatientPackageBalance;
use Modules\Reception\Notifications\UnpaidPackageConsumptionAttemptNotification;
use Modules\Reception\Services\PatientPackage\PatientPackageService;
use Modules\Setup\Enums\PackageTypeEnum;
use Modules\Setup\Models\Package;
use Modules\Setup\Models\Setting;
use Tests\TestCase;

class UnpaidPackageConsumptionNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sends_notification_when_unpaid_package_consumption_limit_exceeded(): void
    {
        // 1. إنشاء مستخدم مدير وموظف استقبال
        $admin = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin_test_' . uniqid() . '@clinic.com',
            'phone' => '010' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $employee = User::create([
            'name' => 'Sarah Receptionist',
            'email' => 'sarah_test_' . uniqid() . '@clinic.com',
            'phone' => '011' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::RECEPTIONIST,
            'is_active' => true,
        ]);

        $this->actingAs($employee);

        // 2. إنشاء مريض وباقة
        $patient = Patient::create([
            'name' => 'Mohamed Ali',
            'phone' => '010' . rand(10000000, 99999999),
        ]);

        $department = \Modules\Setup\Models\Department::firstOrCreate(
            ['name' => 'Dermatology'],
            ['is_active' => true]
        );

        $package = Package::create([
            'name' => 'Laser 10 Sessions',
            'department_id' => $department->id,
            'type' => PackageTypeEnum::SESSIONS,
            'price' => 1000,
            'is_active' => true,
        ]);

        Setting::updateOrCreate(
            ['key' => 'package_unpaid_consumption_limit_sessions'],
            ['value' => '50', 'type' => 'number', 'display_name' => 'حد استهلاك الجلسات']
        );

        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_id' => $package->id,
            'total_price' => 1000,
            'paid_amount' => 200,
            'remaining_amount' => 800, // مديونية 800 ج
            'status' => PatientPackageStatusEnum::ACTIVE,
            'start_date' => now(),
            'created_by' => $admin->id,
        ]);

        $balance = PatientPackageBalance::create([
            'patient_package_id' => $patientPackage->id,
            'item_type' => \Modules\Setup\Enums\PackageItemTypeEnum::SERVICE,
            'custom_name' => 'جلسة ليزر',
            'total_quantity' => 10,
            'consumed_quantity' => 4, // 40% مستهلك
            'remaining_quantity' => 6,
        ]);

        // طلب استهلاك 2 جلسة إضافية (4 + 2 = 6 / 10 = 60% > 50% limit)
        $result = PatientPackageService::checkUnpaidConsumptionLimit($balance, 2);

        $this->assertNotNull($result);
        $this->assertStringContainsString('الحد المسموح به لاستهلاك باقة', $result);
        $this->assertStringContainsString('800.00', $result);

        // التحقق من وصول الإشعار للمدير وللموظف
        $adminNotifications = $admin->notifications()->get();
        $this->assertTrue($adminNotifications->isNotEmpty(), 'Admin should receive notification');

        $notificationData = $adminNotifications->first()->data;
        $this->assertEquals('package_debt_limit', $notificationData['type']);
        $this->assertEquals('Sarah Receptionist', $notificationData['employee_name']);
        $this->assertEquals('Mohamed Ali', $notificationData['patient_name']);
        $this->assertEquals('Laser 10 Sessions', $notificationData['package_name']);
        $this->assertStringContainsString('Sarah Receptionist', $notificationData['message']);
        $this->assertStringContainsString('Mohamed Ali', $notificationData['message']);
        $this->assertStringContainsString('Laser 10 Sessions', $notificationData['message']);

        // التحقق من وصول الإشعار أيضاً للموظف الذي قام بالمحاولة
        $employeeNotifications = $employee->notifications()->get();
        $this->assertTrue($employeeNotifications->isNotEmpty(), 'Employee should also receive notification');
    }

    public function test_no_notification_when_package_is_fully_paid(): void
    {
        $admin = User::create([
            'name' => 'Admin Boss 2',
            'email' => 'admin_test2_' . uniqid() . '@clinic.com',
            'phone' => '010' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $patient = Patient::create([
            'name' => 'Ahmed Mahmoud',
            'phone' => '010' . rand(10000000, 99999999),
        ]);

        $department = \Modules\Setup\Models\Department::firstOrCreate(
            ['name' => 'Dermatology'],
            ['is_active' => true]
        );

        $package = Package::create([
            'name' => 'Laser 5 Sessions',
            'department_id' => $department->id,
            'type' => PackageTypeEnum::SESSIONS,
            'price' => 500,
            'is_active' => true,
        ]);

        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_id' => $package->id,
            'total_price' => 500,
            'paid_amount' => 500,
            'remaining_amount' => 0, // مسددة بالكامل
            'status' => PatientPackageStatusEnum::ACTIVE,
            'start_date' => now(),
            'created_by' => $admin->id,
        ]);

        $balance = PatientPackageBalance::create([
            'patient_package_id' => $patientPackage->id,
            'item_type' => \Modules\Setup\Enums\PackageItemTypeEnum::SERVICE,
            'custom_name' => 'جلسة ليزر',
            'total_quantity' => 5,
            'consumed_quantity' => 4,
            'remaining_quantity' => 1,
        ]);

        $result = PatientPackageService::checkUnpaidConsumptionLimit($balance, 1);
        $this->assertNull($result);
        $this->assertEquals(0, $admin->notifications()->count());
    }
}
