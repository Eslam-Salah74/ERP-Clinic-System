<?php

namespace Modules\Reception\Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HR\Enums\ExpenseCategoryEnum;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Models\Expense;
use Modules\HR\Models\Payroll;
use Modules\Inventory\Enums\ItemTypeEnum;
use Modules\Inventory\Enums\ItemUnitEnum;
use Modules\Inventory\Models\Item;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\InvoiceItem;
use Modules\Reception\Models\Patient;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;
use Modules\Setup\Models\Service;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $doctorUser;
    protected Department $department;
    protected Service $consultationService;
    protected Service $deviceService;
    protected Item $consumableItem;
    protected Shift $shift;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إنشاء دور Super Admin وإعطائه كل الصلاحيات
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'api']);

        $this->adminUser = User::create([
            'name' => 'مدير النظام التجريبي',
            'phone' => '01199999999',
            'password' => bcrypt('password123'),
            'type' => UserType::ADMIN,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole($superAdminRole);

        // 2. إنشاء قسم وطبيب
        $this->department = Department::create([
            'name' => 'قسم الجلدية والليزر التجريبي',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'د. أحمد محمود',
            'phone' => '01188888888',
            'password' => bcrypt('password123'),
            'type' => UserType::DOCTOR,
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        // 3. إنشاء أصناف مخزنية وخدمات (كشف وجهاز)
        $this->consumableItem = Item::create([
            'name' => 'تبس ليزر فركشنال',
            'selling_price' => 150.00,
            'unit' => ItemUnitEnum::PIECE,
            'stock_unit' => ItemUnitEnum::PIECE,
            'type' => ItemTypeEnum::CONSUMABLE,
            'current_stock' => 100,
            'is_active' => true,
        ]);

        $this->consultationService = Service::create([
            'name' => 'كشف جلدية استشاري',
            'department_id' => $this->department->id,
            'price' => 400.00,
            'type' => ServiceTypeEnum::CONSULTATION,
            'is_active' => true,
        ]);

        $this->deviceService = Service::create([
            'name' => 'جلسة فراكشنال ليزر كاملة',
            'department_id' => $this->department->id,
            'price' => 1200.00,
            'type' => ServiceTypeEnum::DEVICE,
            'is_active' => true,
        ]);

        // ربط الجهاز بالمستلزم
        $this->deviceService->items()->attach($this->consumableItem->id, [
            'quantity' => 1,
            'price' => 100.00, // تكلفة المستلزم
        ]);

        // 4. إنشاء شفت ومريض وفواتير وسندات ومصروفات
        $this->shift = Shift::create([
            'user_id' => $this->adminUser->id,
            'status' => ShiftStatusEnum::CLOSED,
            'initial_balance' => 1000.00,
            'final_balance' => 2500.00,
            'start_time' => Carbon::now()->subHours(6),
            'end_time' => Carbon::now(),
        ]);

        $this->patient = Patient::create([
            'name' => 'مريض تجريبي',
            'phone' => '01000000001',
        ]);

        // فاتورة كشف + جلسة جهاز
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctorUser->id,
            'shift_id' => $this->shift->id,
            'type' => InvoiceTypeEnum::CONSULTATION,
            'status' => InvoiceStatusEnum::PAID,
            'payment_method' => PaymentMethodEnum::CASH,
            'sub_total' => 1600.00,
            'discount' => 0.00,
            'grand_total' => 1600.00,
            'paid_amount' => 1600.00,
            'remaining_amount' => 0.00,
            'created_by' => $this->adminUser->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'service',
            'service_id' => $this->consultationService->id,
            'item_name' => $this->consultationService->name,
            'unit_price' => 400.00,
            'quantity' => 1,
            'total_price' => 400.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'service',
            'service_id' => $this->deviceService->id,
            'item_name' => $this->deviceService->name,
            'unit_price' => 1200.00,
            'quantity' => 1,
            'total_price' => 1200.00,
            'service_items_ids' => [$this->consumableItem->id],
        ]);

        Transaction::create([
            'transaction_number' => 'TRX-TEST-001',
            'invoice_id' => $invoice->id,
            'shift_id' => $this->shift->id,
            'type' => TransactionTypeEnum::INCOME,
            'payment_method' => PaymentMethodEnum::CASH,
            'amount' => 1600.00,
            'created_by' => $this->adminUser->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // مصروف نثريات من الشفت
        Expense::create([
            'title' => 'مستلزمات بوفيه للشفت',
            'amount' => 100.00,
            'expense_date' => Carbon::today(),
            'category' => ExpenseCategoryEnum::BUFFET,
            'payment_method' => PaymentMethodEnum::CASH,
            'shift_id' => $this->shift->id,
            'created_by' => $this->adminUser->id,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // مسير راتب مصروف
        Payroll::create([
            'user_id' => $this->doctorUser->id,
            'month' => Carbon::now()->format('Y-m'),
            'start_date' => Carbon::now()->startOfMonth(),
            'end_date' => Carbon::now()->endOfMonth(),
            'basic_salary' => 5000.00,
            'gross_salary' => 5000.00,
            'net_salary' => 5000.00,
            'status' => PayrollStatusEnum::PAID,
            'paid_at' => Carbon::now()->subHours(1),
            'paid_by' => $this->adminUser->id,
        ]);
    }

    /**
     * اختبار تقرير الخزنة اليومية
     */
    public function test_daily_safe_report_returns_successful_data(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/daily-safe?shift_id={$this->shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'تقرير الخزنة اليومية ومطابقة النقدية')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'treasury_summary' => [
                        'opening_balance',
                        'cash_collected',
                        'total_collected_revenue',
                        'expected_drawer_balance',
                        'actual_drawer_balance',
                        'difference',
                    ],
                    'operations_breakdown',
                    'shifts',
                    'detailed_movements',
                ]
            ]);
    }

    /**
     * اختبار تقرير الأقسام وأعلى 3 أطباء
     */
    public function test_departments_report_returns_correct_stats_and_top_doctors(): void
    {
        $startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::today()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/departments?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'تقرير إيرادات الأقسام وأعلى 3 أطباء في كل قسم')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'clinic_overview',
                    'departments' => [
                        '*' => [
                            'department_id',
                            'department_name',
                            'total_revenue',
                            'operations_summary',
                            'top_3_doctors',
                        ]
                    ]
                ]
            ]);
    }

    /**
     * اختبار تقرير الأطباء المفصل
     */
    public function test_doctors_report_returns_consultations_services_and_revenue(): void
    {
        $startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::today()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/doctors?start_date={$startDate}&end_date={$endDate}&doctor_id={$this->doctorUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'تقرير أداء وإيرادات الأطباء المفصل')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'doctors' => [
                        '*' => [
                            'doctor_id',
                            'doctor_name',
                            'metrics' => [
                                'consultations_count',
                                'consultations_revenue',
                                'devices_count',
                                'devices_revenue',
                            ],
                            'financial_performance' => [
                                'total_revenue_generated',
                                'clinic_net_share',
                            ]
                        ]
                    ]
                ]
            ]);
    }

    /**
     * اختبار تقرير الأجهزة والمستهلكات والأرباح
     */
    public function test_devices_report_returns_consumables_quantities_and_profits(): void
    {
        $startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::today()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/devices?start_date={$startDate}&end_date={$endDate}&service_id={$this->deviceService->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'تقرير أداء الأجهزة واستهلاك المواد والمستلزمات الطبية وصافي الأرباح')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'devices' => [
                        '*' => [
                            'device_id',
                            'device_name',
                            'financial_performance' => [
                                'device_revenue',
                                'total_consumables_cost',
                                'device_net_profit',
                            ],
                            'consumables_consumed' => [
                                '*' => [
                                    'item_id',
                                    'item_name',
                                    'total_quantity_consumed',
                                    'unit_cost',
                                    'total_cost',
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
    }

    /**
     * اختبار تقرير المصروفات والرواتب
     */
    public function test_expenses_and_salaries_report_returns_data(): void
    {
        $startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::today()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/expenses-salaries?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'تقرير المصروفات التشغيلية والرواتب المنصرفة')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'grand_total_spent' => [
                        'total_expenses',
                        'total_salaries_paid',
                        'total_combined_outflows',
                    ],
                    'expenses_section',
                    'salaries_section',
                ]
            ]);
    }

    /**
     * اختبار التقرير المالي الشامل والأرباح والخسائر
     */
    public function test_financial_summary_report_returns_net_profit_and_pl_statement(): void
    {
        $startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::today()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/financial-summary?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'تقرير الأرباح والخسائر الشامل للمركز الطبي (P&L)')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'bottom_line_profit' => [
                        'net_profit_amount',
                        'net_profit_margin_percent',
                    ],
                    'income_statement' => [
                        'revenues',
                        'cost_of_supplies_cogs',
                        'operating_expenses',
                        'payroll_and_salaries',
                    ]
                ]
            ]);
    }

    /**
     * اختبار كشف الحركات المالية التفصيلي
     */
    public function test_transactions_ledger_returns_ledger_movements_and_running_balance(): void
    {
        $startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::today()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/reports/transactions-ledger?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('data.report_title', 'كشف الحركات والتدفقات المالية التفصيلي (Transactions Ledger)')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'movements_count',
                    'ledger_totals' => [
                        'total_debits_inflows',
                        'total_credits_outflows',
                        'net_cash_flow',
                    ],
                    'ledger_movements' => [
                        '*' => [
                            'timestamp',
                            'category',
                            'ref_number',
                            'debit',
                            'credit',
                            'running_balance',
                        ]
                    ]
                ]
            ]);
    }
}
