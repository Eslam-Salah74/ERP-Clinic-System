<?php

namespace Modules\Reception\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Reception\Http\Requests\Report\DateRangeReportRequest;
use Modules\Reception\Http\Requests\Report\ShiftSafeReportRequest;
use Modules\Reception\Services\Report\DepartmentReportService;
use Modules\Reception\Services\Report\DeviceReportService;
use Modules\Reception\Services\Report\DoctorReportService;
use Modules\Reception\Services\Report\ExpensePayrollReportService;
use Modules\Reception\Services\Report\FinancialReportService;
use Modules\Reception\Services\Report\ShiftSafeReportService;

class ReportController extends Controller implements HasMiddleware
{
    public function __construct(
        protected ShiftSafeReportService $shiftSafeService,
        protected DepartmentReportService $departmentService,
        protected DoctorReportService $doctorService,
        protected DeviceReportService $deviceService,
        protected ExpensePayrollReportService $expensePayrollService,
        protected FinancialReportService $financialService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read reports|manage shifts', only: ['dailySafe', 'shiftSafe']),
            new Middleware('permission:read reports', only: [
                'financialSummary',
                'departments',
                'doctors',
                'devices',
                'expensesAndSalaries',
                'transactionsLedger',
            ]),
        ];
    }

    /**
     * 1. تقرير الخزنة اليومية للموظف / الشفت
     * GET /api/v1/reports/daily-safe
     */
    public function dailySafe(ShiftSafeReportRequest $request)
    {
        return $this->shiftSafeService->getDailySafeReport($request);
    }

    /**
     * تقرير الخزنة لشفت محدد بالمعرف
     * GET /api/v1/reports/shifts/{id}/safe
     */
    public function shiftSafe($id, ShiftSafeReportRequest $request)
    {
        $request->merge(['shift_id' => $id]);
        return $this->shiftSafeService->getDailySafeReport($request);
    }

    /**
     * 2. التقرير المالي الشامل والأرباح والخسائر للمركز (P&L) - كسبنا كام آخر الشهر
     * GET /api/v1/reports/financial-summary
     */
    public function financialSummary(DateRangeReportRequest $request)
    {
        return $this->financialService->getFinancialSummary($request);
    }

    /**
     * 3. تقرير الأقسام الشامل وأعلى 3 أطباء في كل قسم
     * GET /api/v1/reports/departments
     */
    public function departments(DateRangeReportRequest $request)
    {
        return $this->departmentService->getDepartmentsReport($request);
    }

    /**
     * 4. تقرير الأطباء المفصل (كشوفات، خدمات، جلسات، دخل المركز، عمولات، وصافي المركز)
     * GET /api/v1/reports/doctors
     */
    public function doctors(DateRangeReportRequest $request)
    {
        return $this->doctorService->getDoctorsReport($request);
    }

    /**
     * 5. تقرير الأجهزة والمستهلكات والربحية (كميات المستهلكات، تكلفة المواد، دخل الجهاز، وصافي أرباحه)
     * GET /api/v1/reports/devices
     */
    public function devices(DateRangeReportRequest $request)
    {
        return $this->deviceService->getDevicesReport($request);
    }

    /**
     * 6. تقرير المصروفات والمرتبات التفصيلي (صرفنا كام ودفعنا مرتبات كام بكل تصنيفاتها)
     * GET /api/v1/reports/expenses-salaries
     */
    public function expensesAndSalaries(DateRangeReportRequest $request)
    {
        return $this->expensePayrollService->getExpensesAndSalariesReport($request);
    }

    /**
     * 7. سجل الحركات المالية التفصيلي (كل حركة مالية بالتفصيل)
     * GET /api/v1/reports/transactions-ledger
     */
    public function transactionsLedger(DateRangeReportRequest $request)
    {
        return $this->financialService->getTransactionsLedger($request);
    }
}
