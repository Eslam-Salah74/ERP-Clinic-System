<?php

namespace Modules\Reception\Tests\Unit;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Models\Patient;
use Modules\Reception\Models\Shift;
use Modules\Reception\Services\Invoice\InvoiceService;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;
use Modules\Setup\Models\Service;
use Tests\TestCase;

class InvoiceDiscountNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sends_notification_when_discount_applied_on_invoice(): void
    {
        $admin = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin_disc_' . uniqid() . '@clinic.com',
            'phone' => '010' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $employee = User::create([
            'name' => 'Kareem Receptionist',
            'email' => 'kareem_disc_' . uniqid() . '@clinic.com',
            'phone' => '011' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::RECEPTIONIST,
            'is_active' => true,
        ]);

        $this->actingAs($employee);

        // فتح شفت للموظف
        $shift = Shift::create([
            'user_id' => $employee->id,
            'shift_number' => 'SH-' . uniqid(),
            'start_time' => now(),
            'opening_balance' => 0,
            'status' => ShiftStatusEnum::OPEN->value,
        ]);

        $patient = Patient::create([
            'name' => 'Tamer Hosny',
            'phone' => '012' . rand(10000000, 99999999),
        ]);

        $department = Department::firstOrCreate(
            ['name' => 'Dentistry'],
            ['is_active' => true]
        );

        $service = Service::create([
            'name' => 'Teeth Cleaning',
            'department_id' => $department->id,
            'price' => 500,
            'type' => ServiceTypeEnum::SESSION->value,
            'is_active' => true,
        ]);

        $invoiceData = [
            'patient_id' => $patient->id,
            'type' => InvoiceTypeEnum::DIRECT_SALE->value,
            'payment_method' => 'cash',
            'discount' => 100, // خصم 100 ج
            'items' => [
                [
                    'item_type' => 'service',
                    'service_id' => $service->id,
                    'quantity' => 1,
                ]
            ],
        ];

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $response = $invoiceService->store($invoiceData);

        $this->assertEquals(201, $response->getStatusCode());

        // التحقق من وصول الإشعار للمدير وللموظف
        $adminNotifications = $admin->notifications()->get();
        $this->assertTrue($adminNotifications->isNotEmpty(), 'Admin should receive discount notification');

        $notificationData = $adminNotifications->first()->data;
        $this->assertEquals('invoice_discount', $notificationData['type']);
        $this->assertEquals('Kareem Receptionist', $notificationData['employee_name']);
        $this->assertEquals('Tamer Hosny', $notificationData['patient_name']);
        $this->assertEquals(100, $notificationData['discount_amount']);
        $this->assertStringContainsString('Kareem Receptionist', $notificationData['message']);
        $this->assertStringContainsString('Tamer Hosny', $notificationData['message']);
        $this->assertStringContainsString('100.00 ج', $notificationData['message']);

        // التحقق من وصول الإشعار للموظف
        $employeeNotifications = $employee->notifications()->get();
        $this->assertTrue($employeeNotifications->isNotEmpty(), 'Employee should also receive discount notification');
    }

    public function test_no_notification_when_invoice_has_zero_discount(): void
    {
        $admin = User::create([
            'name' => 'Admin Manager 2',
            'email' => 'admin_disc2_' . uniqid() . '@clinic.com',
            'phone' => '010' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $employee = User::create([
            'name' => 'Receptionist Zero',
            'email' => 'zero_disc_' . uniqid() . '@clinic.com',
            'phone' => '011' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::RECEPTIONIST,
            'is_active' => true,
        ]);

        $this->actingAs($employee);

        Shift::create([
            'user_id' => $employee->id,
            'shift_number' => 'SH-' . uniqid(),
            'start_time' => now(),
            'opening_balance' => 0,
            'status' => ShiftStatusEnum::OPEN->value,
        ]);

        $patient = Patient::create([
            'name' => 'Ali Hassan',
            'phone' => '012' . rand(10000000, 99999999),
        ]);

        $department = Department::firstOrCreate(
            ['name' => 'Dentistry'],
            ['is_active' => true]
        );

        $service = Service::create([
            'name' => 'Tooth Extraction',
            'department_id' => $department->id,
            'price' => 300,
            'type' => ServiceTypeEnum::SESSION->value,
            'is_active' => true,
        ]);

        $invoiceData = [
            'patient_id' => $patient->id,
            'type' => InvoiceTypeEnum::DIRECT_SALE->value,
            'payment_method' => 'cash',
            'discount' => 0, // بدون خصم
            'items' => [
                [
                    'item_type' => 'service',
                    'service_id' => $service->id,
                    'quantity' => 1,
                ]
            ],
        ];

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $response = $invoiceService->store($invoiceData);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(0, $admin->notifications()->count());
    }
}
