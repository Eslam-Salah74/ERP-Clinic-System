<?php

namespace Modules\Reception\Services\Report;

use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\ExpenseCategoryEnum;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Models\Expense;
use Modules\HR\Models\Payroll;
use Modules\Reception\Enums\PaymentMethodEnum;

class ExpensePayrollReportService
{
    /**
     * تقرير المصروفات والمرتبات التفصيلي (صرفنا كام ودفعنا مرتبات كام بكل تصنيفاتها)
     */
    public function getExpensesAndSalariesReport($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $category  = $request->input('category');

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd   = $endDate->format('Y-m-d');

        // 1. استعلام المصروفات
        $expensesQuery = Expense::with(['creator', 'shift.user'])
            ->whereBetween('expense_date', [$formattedStart, $formattedEnd]);

        if ($category) {
            $expensesQuery->where('category', $category);
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();

        $totalExpensesAmount = (float) $expenses->sum('amount');

        // تصنيف المصروفات حسب الفئة
        $categoriesNames = [
            ExpenseCategoryEnum::UTILITY->value     => 'فواتير ومرافق (كهرباء، مياه، نت)',
            ExpenseCategoryEnum::BUFFET->value      => 'مستلزمات بوفيه وضيافة',
            ExpenseCategoryEnum::MAINTENANCE->value => 'صيانة، نظافة، أدوات مكتبية',
            ExpenseCategoryEnum::RENT->value        => 'إيجارات مقرات',
            ExpenseCategoryEnum::SALARIES->value    => 'سلف ونثريات مرتبات مباشرة',
            ExpenseCategoryEnum::OTHER->value       => 'مصروفات تشغيلية أخرى',
        ];

        $expensesByCategory = [];
        foreach (ExpenseCategoryEnum::cases() as $catEnum) {
            $catVal = $catEnum->value;
            $catExpenses = $expenses->where('category', $catEnum);
            $catTotal = (float) $catExpenses->sum('amount');

            $expensesByCategory[] = [
                'category_code' => $catVal,
                'category_name' => $categoriesNames[$catVal] ?? $catVal,
                'count'         => $catExpenses->count(),
                'total_amount'  => round($catTotal, 2),
                'percent'       => $totalExpensesAmount > 0 ? round(($catTotal / $totalExpensesAmount) * 100, 2) : 0.00,
            ];
        }

        // تصنيف المصروفات حسب وسيلة السداد (كاش من الدرج vs تحويل/بنك)
        $cashExpensesAmount    = (float) $expenses->filter(fn($e) => $e->payment_method === PaymentMethodEnum::CASH || $e->payment_method === null)->sum('amount');
        $nonCashExpensesAmount = (float) $expenses->filter(fn($e) => $e->payment_method !== null && $e->payment_method !== PaymentMethodEnum::CASH)->sum('amount');

        $itemizedExpenses = $expenses->map(function ($e) use ($categoriesNames) {
            return [
                'expense_id'     => $e->id,
                'date'           => $e->expense_date ? $e->expense_date->format('Y-m-d') : null,
                'category'       => $e->category?->value,
                'category_name'  => $categoriesNames[$e->category?->value ?? ''] ?? 'غير مصنف',
                'amount'         => (float) $e->amount,
                'payment_method' => $e->payment_method?->value ?? 'cash',
                'shift_id'       => $e->shift_id,
                'created_by'     => $e->creator?->name ?? 'غير محدد',
                'title'          => $e->title ?? '',
                'description'    => $e->title ?? $e->notes ?? '',
            ];
        });

        // 2. استعلام مسيرات الرواتب (Payrolls)
        $payrolls = Payroll::with(['user.department', 'payer', 'approver'])
            ->where(function ($query) use ($startDate, $endDate, $formattedStart, $formattedEnd) {
                // مسيرات تم صرفها في هذه الفترة أو تغطي هذه الفترة
                $query->whereBetween('paid_at', [$startDate, $endDate])
                      ->orWhereBetween('start_date', [$formattedStart, $formattedEnd])
                      ->orWhereBetween('end_date', [$formattedStart, $formattedEnd]);
            })
            ->get();

        $paidPayrolls = $payrolls->where('status', PayrollStatusEnum::PAID);

        $totalGrossSalariesPaid     = (float) $paidPayrolls->sum('gross_salary');
        $totalNetSalariesPaid       = (float) $paidPayrolls->sum('net_salary');
        $totalDeductionsPaid        = (float) $paidPayrolls->sum('deductions');
        $totalBasicSalariesPaid     = (float) $paidPayrolls->sum('basic_salary');
        $totalOvertimePaid          = (float) $paidPayrolls->sum('overtime_amount');
        $totalHolidayAllowancesPaid = (float) $paidPayrolls->sum('holiday_allowance_amount');
        $totalServiceCommissionsPaid= (float) $paidPayrolls->sum('service_commissions_amount');
        $totalDeviceCommissionsPaid = (float) $paidPayrolls->sum('device_commissions_amount');
        $totalProductCommissionsPaid= (float) $paidPayrolls->sum('product_commissions_amount');
        $totalTargetBonusesPaid     = (float) $paidPayrolls->sum('target_bonus_amount');

        $itemizedPayrolls = $payrolls->map(function ($p) {
            return [
                'payroll_id'            => $p->id,
                'employee_id'           => $p->user_id,
                'employee_name'         => $p->user?->name ?? 'غير معروف',
                'job_role'              => $p->user?->type?->value ?? 'موظف',
                'department'            => $p->user?->department?->name ?? 'عام',
                'status'                => $p->status?->value ?? 'draft',
                'period_start'          => $p->start_date ? $p->start_date->format('Y-m-d') : $p->month,
                'period_end'            => $p->end_date ? $p->end_date->format('Y-m-d') : $p->month,
                'basic_salary'          => (float) $p->basic_salary,
                'service_commissions'   => (float) $p->service_commissions_amount,
                'device_commissions'    => (float) $p->device_commissions_amount,
                'product_commissions'   => (float) $p->product_commissions_amount,
                'overtime_amount'       => (float) $p->overtime_amount,
                'holiday_allowance'     => (float) $p->holiday_allowance_amount,
                'target_bonus'          => (float) $p->target_bonus_amount,
                'deductions'            => (float) $p->deductions,
                'gross_salary'          => (float) $p->gross_salary,
                'net_salary'            => (float) $p->net_salary,
                'paid_at'               => $p->paid_at ? $p->paid_at->format('Y-m-d H:i:s') : null,
                'paid_by_name'          => $p->payer?->name ?? null,
            ];
        });

        // الإجمالي المشترك للمصروفات والمرتبات
        $totalExpenditures = round($totalExpensesAmount + $totalNetSalariesPaid, 2);

        return API::newInstance()
            ->isOk('تم توليد تقرير المصروفات والرواتب بنجاح')
            ->setData([
                'report_title'       => 'تقرير المصروفات التشغيلية والرواتب المنصرفة',
                'period'             => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $startDate->diffInDays($endDate) + 1,
                ],
                'grand_total_spent'  => [
                    'total_expenses'           => round($totalExpensesAmount, 2),
                    'total_salaries_paid'      => round($totalNetSalariesPaid, 2),
                    'total_combined_outflows'  => $totalExpenditures,
                ],
                'expenses_section'   => [
                    'total_expenses_amount'    => round($totalExpensesAmount, 2),
                    'expenses_vouchers_count'  => $expenses->count(),
                    'cash_paid_from_drawer'    => round($cashExpensesAmount, 2),
                    'non_cash_paid'            => round($nonCashExpensesAmount, 2),
                    'by_category'              => $expensesByCategory,
                    'itemized_expenses'        => $itemizedExpenses,
                ],
                'salaries_section'   => [
                    'total_net_salaries_paid'  => round($totalNetSalariesPaid, 2),
                    'total_gross_salaries'     => round($totalGrossSalariesPaid, 2),
                    'total_deductions'         => round($totalDeductionsPaid, 2),
                    'payrolls_paid_count'      => $paidPayrolls->count(),
                    'components_breakdown'     => [
                        'basic_salaries'           => round($totalBasicSalariesPaid, 2),
                        'service_commissions'      => round($totalServiceCommissionsPaid, 2),
                        'device_commissions'       => round($totalDeviceCommissionsPaid, 2),
                        'product_commissions'      => round($totalProductCommissionsPaid, 2),
                        'overtime_paid'            => round($totalOvertimePaid, 2),
                        'holiday_allowances_paid'  => round($totalHolidayAllowancesPaid, 2),
                        'target_bonuses_paid'      => round($totalTargetBonusesPaid, 2),
                    ],
                    'itemized_payrolls'        => $itemizedPayrolls,
                ],
            ])
            ->build();
    }
}
