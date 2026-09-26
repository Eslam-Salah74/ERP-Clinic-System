<?php

namespace Modules\Reception\Services\Report;

use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Models\Expense;
use Modules\HR\Models\Payroll;
use Modules\Inventory\Models\PurchaseInvoiceItem;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\InvoiceItem;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Setup\Enums\ServiceTypeEnum;

class FinancialReportService
{
    /**
     * تقرير الأرباح والخسائر الشامل للمركز (P&L) - لمعرفة كسبنا كام آخر الشهر بدقة تامة
     */
    public function getFinancialSummary($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd   = $endDate->format('Y-m-d');

        // 1. الفواتير النشطة خلال الفترة
        $invoices = Invoice::with(['items.service', 'patient', 'doctor', 'transactions'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
            ->get();

        // 2. السندات المالية (المقبوضات والمرتجعات) خلال الفترة
        $transactions = Transaction::whereBetween('created_at', [$startDate, $endDate])->get();

        // 3. المصروفات التشغيلية
        $expenses = Expense::whereBetween('expense_date', [$formattedStart, $formattedEnd])->get();

        // 4. مسيرات الرواتب المنصرفة
        $payrolls = Payroll::where(function ($q) use ($startDate, $endDate, $formattedStart, $formattedEnd) {
            $q->whereBetween('paid_at', [$startDate, $endDate])
              ->orWhereBetween('start_date', [$formattedStart, $formattedEnd])
              ->orWhereBetween('end_date', [$formattedStart, $formattedEnd]);
        })->get();

        $paidPayrolls = $payrolls->where('status', PayrollStatusEnum::PAID);

        // --- أ. تفصيل الإيرادات والمبيعات ---
        $grossInvoicedTotal = (float) $invoices->sum('sub_total');
        $totalDiscounts     = (float) $invoices->sum('discount');
        $netInvoicedTotal   = (float) $invoices->sum('grand_total');
        $totalPaidCollected = (float) $invoices->sum('paid_amount');
        $totalRemainingDebt = (float) $invoices->sum('remaining_amount');
        $totalRefunds       = (float) $transactions->where('type', TransactionTypeEnum::REFUND)->sum('amount');

        // تصنيف الإيرادات حسب نوع الخدمة
        $consultationsRev = 0.00;
        $servicesRev      = 0.00;
        $devicesRev       = 0.00;
        $directSalesRev   = 0.00;

        $consultationsCount = 0;
        $servicesCount      = 0;
        $devicesCount       = 0;
        $directSalesCount   = 0;

        // حساب تكلفة البضاعة والمستلزمات المستهلكة (COGS)
        $itemsCosts = $this->loadItemsCosts();
        $totalConsumablesCost = 0.00;

        foreach ($invoices as $inv) {
            foreach ($inv->items as $item) {
                $itemTotal = (float) $item->total_price;
                $itemQty   = (int) $item->quantity;

                if ($item->item_type === 'product') {
                    $directSalesRev   += $itemTotal;
                    $directSalesCount += $itemQty;

                    // تكلفة المنتج المباع
                    $unitCost = $itemsCosts[$item->product_id] ?? 0.00;
                    $totalConsumablesCost += ($unitCost * $itemQty);
                } elseif ($item->service) {
                    $svc = $item->service;
                    if ($svc->type === ServiceTypeEnum::CONSULTATION) {
                        $consultationsRev   += $itemTotal;
                        $consultationsCount += $itemQty;
                    } elseif ($svc->type === ServiceTypeEnum::DEVICE) {
                        $devicesRev   += $itemTotal;
                        $devicesCount += $itemQty;
                    } else {
                        $servicesRev   += $itemTotal;
                        $servicesCount += $itemQty;
                    }

                    // حساب تكلفة مستلزمات الخدمة من service_items
                    if ($svc->items) {
                        foreach ($svc->items as $sItem) {
                            $qtyPerSvc = (float) ($sItem->pivot->quantity ?? 1);
                            $uCost = (float) ($sItem->pivot->price > 0 ? $sItem->pivot->price : ($itemsCosts[$sItem->id] ?? 0));
                            $totalConsumablesCost += ($qtyPerSvc * $itemQty * $uCost);
                        }
                    }
                }
            }
        }

        // المتحصلات الفعلية حسب وسيلة الدفع
        $cashInflows      = (float) $transactions->where('payment_method', PaymentMethodEnum::CASH)->where('type', TransactionTypeEnum::INCOME)->sum('amount');
        $visaInflows      = (float) $transactions->where('payment_method', PaymentMethodEnum::VISA)->where('type', TransactionTypeEnum::INCOME)->sum('amount');
        $walletInflows    = (float) $transactions->where('payment_method', PaymentMethodEnum::WALLET)->where('type', TransactionTypeEnum::INCOME)->sum('amount');
        $insuranceInflows = (float) $transactions->where('payment_method', PaymentMethodEnum::INSURANCE)->where('type', TransactionTypeEnum::INCOME)->sum('amount');

        // صافي الإيرادات بعد المرتجعات
        $netRealizedRevenue = max(0, $netInvoicedTotal - $totalRefunds);

        // --- ب. تفصيل التكاليف والمصروفات ---
        $totalOperatingExpenses = (float) $expenses->sum('amount');
        $totalSalariesPaid      = (float) $paidPayrolls->sum('net_salary');
        $totalDirectCostCOGS    = round($totalConsumablesCost, 2);

        $totalClinicExpenditures = round($totalDirectCostCOGS + $totalOperatingExpenses + $totalSalariesPaid, 2);

        // --- ج. حساب مجمل وصافي الربح (كسبنا كام آخر الشهر) ---
        $grossProfit = round($netRealizedRevenue - $totalDirectCostCOGS, 2);
        $grossMargin = ($netRealizedRevenue > 0) ? round(($grossProfit / $netRealizedRevenue) * 100, 2) : 0.00;

        $netOperatingProfit = round($grossProfit - $totalOperatingExpenses - $totalSalariesPaid, 2);
        $netProfitMargin    = ($netRealizedRevenue > 0) ? round(($netOperatingProfit / $netRealizedRevenue) * 100, 2) : 0.00;

        // حالة الأداء المالي
        $financialHealth = 'ممتاز (أرباح قوية)';
        if ($netOperatingProfit < 0) {
            $financialHealth = 'خسارة تشغيلية (المصروفات تجاوزت الإيرادات)';
        } elseif ($netProfitMargin < 15) {
            $financialHealth = 'هامش ربح منخفض (يتطلب ترشيد المصروفات)';
        } elseif ($netProfitMargin < 30) {
            $financialHealth = 'جيد ومستقر';
        }

        $daysCount = $startDate->diffInDays($endDate) + 1;
        $uniquePatients = $invoices->pluck('patient_id')->filter()->unique()->count();

        return API::newInstance()
            ->isOk('تم توليد التقرير المالي الشامل بنجاح')
            ->setData([
                'report_title'           => 'تقرير الأرباح والخسائر الشامل للمركز الطبي (P&L)',
                'period'                 => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $daysCount,
                ],
                'bottom_line_profit'     => [
                    'net_profit_amount'        => $netOperatingProfit, // كسبنا كام آخر الشهر
                    'net_profit_margin_percent'=> $netProfitMargin,
                    'performance_status'       => $netOperatingProfit >= 0 ? 'profit' : 'loss',
                    'financial_health'         => $financialHealth,
                    'profit_per_day'           => $daysCount > 0 ? round($netOperatingProfit / $daysCount, 2) : 0.00,
                    'profit_per_patient'       => $uniquePatients > 0 ? round($netOperatingProfit / $uniquePatients, 2) : 0.00,
                ],
                'income_statement'       => [
                    'revenues' => [
                        'gross_invoiced'           => round($grossInvoicedTotal, 2),
                        'discounts_given'          => round($totalDiscounts, 2),
                        'net_invoiced'             => round($netInvoicedTotal, 2),
                        'refunds_deducted'         => round($totalRefunds, 2),
                        'net_realized_revenue'     => round($netRealizedRevenue, 2),
                        'by_category'              => [
                            'consultations' => ['count' => $consultationsCount, 'revenue' => round($consultationsRev, 2)],
                            'services'      => ['count' => $servicesCount,      'revenue' => round($servicesRev, 2)],
                            'devices'       => ['count' => $devicesCount,       'revenue' => round($devicesRev, 2)],
                            'direct_sales'  => ['count' => $directSalesCount,   'revenue' => round($directSalesRev, 2)],
                        ],
                        'collection_breakdown'     => [
                            'cash'                  => round($cashInflows, 2),
                            'visa'                  => round($visaInflows, 2),
                            'wallet'                => round($walletInflows, 2),
                            'insurance'             => round($insuranceInflows, 2),
                            'total_collected'       => round($totalPaidCollected, 2),
                            'remaining_uncollected' => round($totalRemainingDebt, 2),
                            'collection_rate_pct'   => $netInvoicedTotal > 0 ? round(($totalPaidCollected / $netInvoicedTotal) * 100, 2) : 0.00,
                        ],
                    ],
                    'cost_of_supplies_cogs' => [
                        'total_cogs_amount'        => $totalDirectCostCOGS,
                        'gross_profit'             => $grossProfit,
                        'gross_profit_margin_pct'  => $grossMargin,
                    ],
                    'operating_expenses'    => [
                        'total_expenses_amount'    => round($totalOperatingExpenses, 2),
                    ],
                    'payroll_and_salaries'  => [
                        'total_salaries_paid'      => round($totalSalariesPaid, 2),
                        'payrolls_count'           => $paidPayrolls->count(),
                    ],
                    'total_clinic_expenditures' => $totalClinicExpenditures,
                    'net_operating_profit'      => $netOperatingProfit,
                ],
                'operational_kpis'       => [
                    'total_invoices_issued'     => $invoices->count(),
                    'total_unique_patients'     => $uniquePatients,
                    'average_invoice_value'     => $invoices->count() > 0 ? round($netInvoicedTotal / $invoices->count(), 2) : 0.00,
                    'average_revenue_per_day'   => $daysCount > 0 ? round($netRealizedRevenue / $daysCount, 2) : 0.00,
                ],
            ])
            ->build();
    }

    /**
     * سجل الحركات المالية التفصيلي (كل حركة مالية بالتفصيل)
     */
    public function getTransactionsLedger($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $method    = $request->input('payment_method');

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd   = $endDate->format('Y-m-d');

        $movements = collect();

        // 1. سندات المقبوضات والمرتجعات (Transactions)
        $trxQuery = Transaction::with(['invoice.patient', 'invoice.doctor', 'creator', 'shift.user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($method) {
            $trxQuery->where('payment_method', $method);
        }

        foreach ($trxQuery->get() as $t) {
            $isIncome = ($t->type === TransactionTypeEnum::INCOME);
            $amount   = (float) $t->amount;

            $movements->push([
                'timestamp'        => $t->created_at ? $t->created_at->format('Y-m-d H:i:s') : null,
                'category'         => $isIncome ? 'إيراد فواتير' : 'مرتجع مالي',
                'ref_number'       => $t->transaction_number ?? ($t->invoice?->invoice_number ?? "TRX-{$t->id}"),
                'party_name'       => $t->invoice?->patient?->name ?? 'مريض',
                'doctor_or_staff'  => $t->invoice?->doctor?->name ?? '-',
                'shift_id'         => $t->shift_id,
                'recorded_by'      => $t->creator?->name ?? 'كاشير',
                'payment_method'   => $t->payment_method?->value ?? 'cash',
                'debit'            => $isIncome ? $amount : 0.00,   // وارد / مدين
                'credit'           => !$isIncome ? $amount : 0.00,  // صادر / دائن
                'net_flow'         => $isIncome ? $amount : -$amount,
                'description'      => $t->description ?? ($isIncome ? 'سداد فاتورة مريض' : 'مرتجع فاتورة مريض'),
            ]);
        }

        // 2. المصروفات (Expenses)
        $expQuery = Expense::with(['creator', 'shift.user'])
            ->whereBetween('expense_date', [$formattedStart, $formattedEnd]);

        if ($method) {
            $expQuery->where('payment_method', $method);
        }

        foreach ($expQuery->get() as $e) {
            $amount = (float) $e->amount;
            $movements->push([
                'timestamp'        => $e->created_at ? $e->created_at->format('Y-m-d H:i:s') : ($e->expense_date ? $e->expense_date->format('Y-m-d 12:00:00') : null),
                'category'         => 'مصروفات تشغيلية (' . ($e->category?->value ?? 'عام') . ')',
                'ref_number'       => "EXP-{$e->id}",
                'party_name'       => $e->title ?? 'جهة الصرف',
                'doctor_or_staff'  => '-',
                'shift_id'         => $e->shift_id,
                'recorded_by'      => $e->creator?->name ?? 'الإدارة',
                'payment_method'   => $e->payment_method?->value ?? 'cash',
                'debit'            => 0.00,
                'credit'           => $amount,
                'net_flow'         => -$amount,
                'description'      => $e->notes ?? $e->title ?? 'مصروف من الخزينة',
            ]);
        }

        // 3. صرف الرواتب (Payrolls)
        $payrollQuery = Payroll::with(['user', 'payer'])
            ->where('status', PayrollStatusEnum::PAID)
            ->whereBetween('paid_at', [$startDate, $endDate]);

        foreach ($payrollQuery->get() as $p) {
            $amount = (float) $p->net_salary;
            $movements->push([
                'timestamp'        => $p->paid_at ? $p->paid_at->format('Y-m-d H:i:s') : null,
                'category'         => 'صرف مرتبات وعمولات',
                'ref_number'       => "PAY-{$p->id}",
                'party_name'       => $p->user?->name ?? 'موظف',
                'doctor_or_staff'  => $p->user?->name ?? '-',
                'shift_id'         => null,
                'recorded_by'      => $p->payer?->name ?? 'المدير المالي',
                'payment_method'   => 'bank_transfer',
                'debit'            => 0.00,
                'credit'           => $amount,
                'net_flow'         => -$amount,
                'description'      => "صرف راتب شهر ({$p->month}) للموظف {$p->user?->name}",
            ]);
        }

        // ترتيب الحركات وحساب الرصيد التراكمي (Running Balance)
        $sorted = $movements->sortBy('timestamp')->values();

        $runningBalance = 0.00;
        $totalDebits    = 0.00;
        $totalCredits   = 0.00;

        $ledgerWithBalance = $sorted->map(function ($item) use (&$runningBalance, &$totalDebits, &$totalCredits) {
            $runningBalance += $item['net_flow'];
            $totalDebits    += $item['debit'];
            $totalCredits   += $item['credit'];

            $item['running_balance'] = round($runningBalance, 2);
            return $item;
        });

        return API::newInstance()
            ->isOk('تم جلب كشف الحركات المالية التفصيلي بنجاح')
            ->setData([
                'report_title'       => 'كشف الحركات والتدفقات المالية التفصيلي (Transactions Ledger)',
                'period'             => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                ],
                'movements_count'    => $ledgerWithBalance->count(),
                'ledger_totals'      => [
                    'total_debits_inflows'   => round($totalDebits, 2),
                    'total_credits_outflows' => round($totalCredits, 2),
                    'net_cash_flow'          => round($runningBalance, 2),
                ],
                'ledger_movements'   => $ledgerWithBalance,
            ])
            ->build();
    }

    /**
     * تحميل متوسط أو أحدث سعر شراء لكل صنف مخزني
     */
    private function loadItemsCosts(): array
    {
        $costs = [];

        $purchasePrices = PurchaseInvoiceItem::select('item_id', 'purchase_price')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('item_id')
            ->map(fn($group) => (float) $group->first()->purchase_price)
            ->toArray();

        foreach ($purchasePrices as $itemId => $price) {
            $costs[$itemId] = $price;
        }

        return $costs;
    }
}
