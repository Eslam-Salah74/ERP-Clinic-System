<?php

namespace Modules\Reception\Tests\Unit;

use App\Enums\UserType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\HR\Enums\ExpenseCategoryEnum;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Models\Expense;
use Modules\HR\Models\Payroll;
use Modules\HR\Models\StaffContract;
use Modules\Inventory\Enums\ItemTypeEnum;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\PurchaseInvoiceItem;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Http\Requests\Report\DateRangeReportRequest;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\Patient;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Reception\Services\Report\DashboardHomeReportService;
use Modules\Reception\Services\Report\DeviceReportService;
use Modules\Reception\Services\Report\FinancialReportService;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;
use Modules\Setup\Models\Service;
use Tests\TestCase;

class ComprehensiveReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $doctor;
    protected Department $department;
    protected Service $deviceService;
    protected Item $consumableItem;
    protected Patient $patient;
    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin_rep_' . uniqid() . '@clinic.com',
            'phone' => '010' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::ADMIN,
            'is_active' => true,
        ]);

        $this->department = Department::firstOrCreate(
            ['name' => 'Laser & Aesthetics'],
            ['is_active' => true]
        );

        $this->doctor = User::create([
            'name' => 'Dr. Ahmed Samy',
            'email' => 'dr_samy_' . uniqid() . '@clinic.com',
            'phone' => '011' . rand(10000000, 99999999),
            'password' => '123456',
            'type' => UserType::DOCTOR,
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        // عقد الطبيب: 10% عمولة على الخدمات
        $contract = StaffContract::create([
            'user_id' => $this->doctor->id,
            'basic_salary' => 5000,
            'other_service_commission_percentage' => 10, // 10% عمولة
            'is_active' => true,
            'contract_start_date' => now()->subMonth(),
        ]);

        $this->shift = Shift::create([
            'user_id' => $this->admin->id,
            'shift_number' => 'SH-' . uniqid(),
            'start_time' => now(),
            'opening_balance' => 0,
            'status' => ShiftStatusEnum::OPEN->value,
        ]);

        $this->patient = Patient::create([
            'name' => 'Sara Mahmoud',
            'phone' => '012' . rand(10000000, 99999999),
        ]);

        // صنف مخزني: جل ليزر (سعر الشراء 50 ج)
        $this->consumableItem = Item::create([
            'name' => 'Laser Gel 500ml',
            'selling_price' => 100,
            'current_stock' => 50,
            'is_active' => true,
            'type' => ItemTypeEnum::CONSUMABLE,
        ]);

        $supplier = \Modules\Inventory\Models\Supplier::create([
            'name' => 'Medical Supplies Co',
            'company_name' => 'MedSupply',
            'phone' => '01099999999',
            'is_active' => true,
        ]);

        // فاتورة شراء للصنف: 20 عبوة بسعر 50 ج = 1000 ج
        $purchaseInv = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'total_amount' => 1000,
            'notes' => 'شراء مستلزمات مخزن',
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $purchaseInv->id,
            'item_id' => $this->consumableItem->id,
            'quantity' => 20,
            'purchase_price' => 50,
            'total_price' => 1000,
        ]);

        // خدمة جهاز: جهاز الليزر (سعر الجلسة 500 ج)
        $this->deviceService = Service::create([
            'name' => 'Candela Laser Device',
            'department_id' => $this->department->id,
            'price' => 500,
            'type' => ServiceTypeEnum::DEVICE,
            'is_active' => true,
        ]);

        // ربط الجهاز بمستهلك مخزني: كل جلسة تستهلك عبوة جل واحدة بسعر 50 ج
        $this->deviceService->items()->attach($this->consumableItem->id, [
            'quantity' => 1,
            'price' => 50,
        ]);
    }

    /**
     * اختبار تقرير الأجهزة: فحص حساب (دخل الجهاز + استهلاك المخزن + عمولة الطبيب + صافي الربح)
     */
    public function test_device_report_calculates_revenue_consumables_commissions_and_net_profit(): void
    {
        // 1. إنشاء فاتورة جلسة على هذا الجهاز بمبلغ 500 ج للطبيب
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'shift_id' => $this->shift->id,
            'type' => InvoiceTypeEnum::SESSION->value,
            'status' => InvoiceStatusEnum::PAID->value,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'sub_total' => 500,
            'discount' => 0,
            'grand_total' => 500,
            'paid_amount' => 500,
            'remaining_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'service_id' => $this->deviceService->id,
            'item_name' => 'جلسة ليزر كانديلا',
            'unit_price' => 500,
            'quantity' => 1,
            'total_price' => 500,
            'item_type' => 'service',
        ]);

        Transaction::create([
            'transaction_number' => 'TRX-' . uniqid(),
            'invoice_id' => $invoice->id,
            'shift_id' => $this->shift->id,
            'type' => TransactionTypeEnum::INCOME->value,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'amount' => 500,
            'created_by' => $this->admin->id,
        ]);

        // 2. طلب تقرير الجهاز
        $request = new DateRangeReportRequest();
        $request->merge([
            'period_preset' => 'today',
            'device_id'     => $this->deviceService->id,
        ]);
        $request->setMethod('GET');

        /** @var DeviceReportService $deviceService */
        $deviceService = app(DeviceReportService::class);
        $response = $deviceService->getDevicesReport($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['is_single_device']);
        $deviceData = $data['devices'];

        // التأكد من الحسابات الدقيقة:
        // دخل الجهاز: 500 ج
        $this->assertEquals(500, $deviceData['financial_performance']['gross_revenue']);

        // تكلفة مستهلكات المخزن: 50 ج
        $this->assertEquals(50, $deviceData['financial_performance']['consumables_cost']);
        $this->assertNotEmpty($deviceData['consumables_consumed']);
        $this->assertEquals('Laser Gel 500ml', $deviceData['consumables_consumed'][0]['item_name']);
        $this->assertEquals(1, $deviceData['consumables_consumed'][0]['total_quantity_consumed']);
        $this->assertEquals(50, $deviceData['consumables_consumed'][0]['total_cost']);

        // عمولة الطبيب: 10% من 500 = 50 ج
        $this->assertEquals(50, $deviceData['financial_performance']['commissions_paid']);

        // صافي ربح الجهاز للمركز: 500 - 50 (مستهلكات) - 50 (عمولة) = 400 ج
        $this->assertEquals(400, $deviceData['financial_performance']['net_profit']);
        $this->assertEquals(80, $deviceData['financial_performance']['profit_margin_percent']);

        // التأكد من وجود الصياغة النصية التوضيحية
        $this->assertStringContainsString('500', $deviceData['summary_narrative']);
        $this->assertStringContainsString('400', $deviceData['summary_narrative']);
    }

    /**
     * اختبار لوحة تحكم الصفحة الرئيسية (Home Dashboard) والـ 6 كروت والـ Period Presets
     */
    public function test_home_dashboard_summary_with_kpi_cards_and_presets(): void
    {
        // إضافة مصروف تشغيلي
        Expense::create([
            'title' => 'Electricity Bill',
            'category' => ExpenseCategoryEnum::UTILITY,
            'amount' => 150,
            'expense_date' => Carbon::today()->toDateString(),
            'notes' => 'فاتورة كهرباء اليوم',
            'created_by' => $this->admin->id,
        ]);

        $request = new DateRangeReportRequest();
        $request->merge([
            'period_preset' => 'today',
        ]);

        /** @var DashboardHomeReportService $homeService */
        $homeService = app(DashboardHomeReportService::class);
        $response = $homeService->getHomeSummary($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true)['data'];

        $this->assertArrayHasKey('executive_kpi_cards', $data);
        $this->assertArrayHasKey('inventory_multi_month_clarity', $data);
        $this->assertArrayHasKey('today_quick_pulse', $data);

        // التحقق من كروت المؤشرات الستة
        $kpis = $data['executive_kpi_cards'];
        $this->assertArrayHasKey('net_profit', $kpis);
        $this->assertArrayHasKey('gross_realized_revenue', $kpis);
        $this->assertArrayHasKey('total_cash_outflows', $kpis);
        $this->assertArrayHasKey('inventory_consumables_cogs', $kpis);
        $this->assertArrayHasKey('operating_expenses', $kpis);
        $this->assertArrayHasKey('salaries_and_commissions', $kpis);

        // التحقق من صندوق إيضاح مشتريات المخزون
        $invClarity = $data['inventory_multi_month_clarity'];
        $this->assertFalse($invClarity['is_deficit']);
        $this->assertStringContainsString('شراء بضاعة تكفي فترات مستقبلية لا يُعد عجزاً', $invClarity['clarification']);
    }

    /**
     * اختبار التقرير المالي P&L مع توضيح فواتير الشراء متعددة الشهور
     */
    public function test_financial_summary_explains_multi_month_purchases(): void
    {
        $request = new DateRangeReportRequest();
        $request->merge([
            'period_preset' => 'this_month',
        ]);

        /** @var FinancialReportService $financialService */
        $financialService = app(FinancialReportService::class);
        $response = $financialService->getFinancialSummary($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true)['data'];

        $this->assertArrayHasKey('inventory_purchases_and_assets', $data);
        $invBox = $data['inventory_purchases_and_assets'];

        $this->assertFalse($invBox['is_deficit']);
        $this->assertGreaterThanOrEqual(1000, $invBox['purchases_during_period']);
        $this->assertStringContainsString('لا يمثل عجزاً أو خسارة', $invBox['accounting_clarification']);
    }
}
