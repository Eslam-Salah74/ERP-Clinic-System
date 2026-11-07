<?php

namespace Modules\Reception\Services\Report;

use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Models\Expense;
use Modules\HR\Models\Payroll;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseInvoice;
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
     * تقرير الأرباح والخسائر الشامل للمركز (P&L) مع إيضاح معالجة المشتريات والمخزون وحركة الخزينة
     */
    public function getFinancialSummary($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd   = $endDate->format('Y-m-d');

        // 1. الفواتير النشطة خلال الفترة
        $invoicesQuery = Invoice::with(['items.service.items', 'patient', 'doctor', 'transactions'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value);

        if ($deptId = $request->input('department_id')) {
            $invoicesQuery->whereHas('items.service', fn($q) => $q->where('department_id', $deptId));
        }

        if ($docId = $request->input('doctor_id')) {
            $invoicesQuery->where('doctor_id', $docId);
        }

        $invoices = $invoicesQuery->get();

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

        // 5. فواتير شراء المستلزمات والمخزون خلال الفترة
        $purchasesTotal = (float) PurchaseInvoice::whereBetween('created_at', [$startDate, $endDate])->sum('total_amount');
        $purchasesCount = PurchaseInvoice::whereBetween('created_at', [$startDate, $endDate])->count();

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

        // صافي الإيرادات المحققة بعد المرتجعات
        $netRealizedRevenue = max(0, $netInvoicedTotal - $totalRefunds);

        // --- ب. تفصيل التكاليف والمصروفات ---
        $totalOperatingExpenses = (float) $expenses->sum('amount');
        $totalSalariesPaid      = (float) $paidPayrolls->sum('net_salary');
        $totalDirectCostCOGS    = round($totalConsumablesCost, 2);

        // --- ج. حساب مجمل وصافي الربح الحقيقي للمركز (كسب كام / خسر كام) ---
        $grossProfit = round($netRealizedRevenue - $totalDirectCostCOGS, 2);
        $grossMargin = ($netRealizedRevenue > 0) ? round(($grossProfit / $netRealizedRevenue) * 100, 2) : 0.00;

        $netOperatingProfit = round($grossProfit - $totalOperatingExpenses - $totalSalariesPaid, 2);
        $netProfitMargin    = ($netRealizedRevenue > 0) ? round(($netOperatingProfit / $netRealizedRevenue) * 100, 2) : 0.00;

        // حالة الأداء المالي
        $performanceStatus = $netOperatingProfit >= 0 ? 'profit' : 'loss';
        $financialHealth = match (true) {
            $netOperatingProfit < 0 => 'خسارة تشغيلية (المصروفات والمستهلكات تجاوزت الإيرادات)',
            $netProfitMargin < 15   => 'هامش ربح منخفض (يتطلب ترشيد المصروفات)',
            $netProfitMargin < 30   => 'أداء مالي جيد ومستقر',
            default                 => 'أداء مالي ممتاز (أرباح قوية)',
        };

        // --- د. إجمالي المدفوعات والسيولة الخارجة كاش (دفع كام) ---
        $totalCashOutflows = round($totalOperatingExpenses + $totalSalariesPaid, 2);

        // --- هـ. تقييم المخزون المتبقي حالياً ---
        $allItems = Item::where('is_active', true)->get();
        $currentTotalStockAssetValue = 0.00;
        foreach ($allItems as $itm) {
            $cost = $itemsCosts[$itm->id] ?? (float) $itm->selling_price;
            $currentTotalStockAssetValue += ((float) $itm->current_stock * $cost);
        }
        $currentTotalStockAssetValue = round($currentTotalStockAssetValue, 2);

        // توضيح فواتير الشراء متعددة الشهور (إجابة حاسمة لتساؤل المالك)
        $unconsumedGain = max(0, $purchasesTotal - $totalDirectCostCOGS);

        $daysCount = $startDate->diffInDays($endDate) + 1;
        $uniquePatients = $invoices->pluck('patient_id')->filter()->unique()->count();

        return API::newInstance()
            ->isOk('تم توليد التقرير المالي الشامل والأرباح والخسائر بنجاح')
            ->setData([
                'report_title'           => 'تقرير الأرباح والخسائر الشامل للمركز الطبي (P&L & Cash Flow)',
                'period'                 => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $daysCount,
                    'preset'     => $request->input('period_preset', 'custom'),
                ],

                // 1. كروت المؤشرات الستة الأساسية للمدير (Executive 6 KPIs)
                'executive_kpis'         => [
                    'net_profit'                 => $netOperatingProfit,      // 1. كسب كام (صافي الربح الحقيقي)
                    'performance_status'         => $performanceStatus,       // رابح أم خاسر
                    'net_profit_margin_percent'  => $netProfitMargin,         // 2. خسر كام / نسبة الهامش
                    'financial_health'           => $financialHealth,
                    'gross_realized_revenue'     => round($netRealizedRevenue, 2), // إجمالي الدخل المحقق
                    'total_cash_outflows'        => $totalCashOutflows,       // 3. دفع كام كاش (مصروفات + رواتب)
                    'inventory_consumables_cogs' => $totalDirectCostCOGS,     // 4. الاستهلاكات المخزنية بالجلسات
                    'operating_expenses'         => round($totalOperatingExpenses, 2), // 5. المصروفات التشغيلية
                    'salaries_and_commissions'   => round($totalSalariesPaid, 2),      // 6. الرواتب والعمولات المنصرفة
                ],

                // 2. قسم التوضيح الحاسم لمشتريات المخزون لعدة شهور
                'inventory_purchases_and_assets' => [
                    'section_title'              => 'المعالجة المحاسبية لفواتير الشراء ورصيد المخزون',
                    'purchases_during_period'    => round($purchasesTotal, 2),  // فواتير الشراء خلال الفترة (مثلاً 20 ألف)
                    'purchases_invoices_count'   => $purchasesCount,
                    'consumed_in_sessions_cogs'  => $totalDirectCostCOGS,       // المستهلك الفعلي بالجلسات والمخصوم من الربح (مثلاً 6.6 ألف)
                    'unconsumed_inventory_asset' => round($unconsumedGain, 2),  // بضاعة متبقية تخدم الشهور القادمة (مثلاً 13.4 ألف)
                    'current_total_stock_value'  => $currentTotalStockAssetValue, // إجمالي قيمة البضاعة الموجودة في المخزن بالكامل الآن
                    'is_deficit'                 => false,                      // ليس عجزاً نهائياً!
                    'accounting_clarification'   => 'شراء بضاعة ومستلزمات طبية تكفي عدة أشهر لا يمثل عجزاً أو خسارة، بل هو تحويل للسيولة إلى أصل مالي متداول (مخزون بضاعة). يُخصم من أرباح هذا الشهر فقط ما تم استهلاكه فعلياً بالجلسات، بينما يظل باقي الرصيد أصلاً مملوكاً للمركز يخدم الفترات القادمة دون الحاجة للشراء مجدداً.',
                ],

                // 3. قائمة الدخل التفصيلية (Income Statement)
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
                    'net_operating_profit'      => $netOperatingProfit,
                ],

                // 4. تسوية حركة الخزينة (Cash Flow vs P&L Reconciliation)
                'cash_flow_reconciliation' => [
                    'net_profit_accrual'        => $netOperatingProfit,
                    'total_cash_collected'      => round($totalPaidCollected, 2),
                    'total_cash_paid_out'       => $totalCashOutflows,
                    'net_cash_movement'         => round($totalPaidCollected - $totalCashOutflows, 2),
                    'explanation'               => 'صافي الربح يعتمد على الإيرادات مطروحاً منها المستهلكات الفعلية، بينما حركة الخزينة توضح النقدية الداخلة والخارجة فقط.',
                ],

                // 5. مؤشرات تشغيلية سريعة
                'operational_kpis'       => [
                    'total_invoices_issued'     => $invoices->count(),
                    'total_unique_patients'     => $uniquePatients,
                    'average_invoice_value'     => $invoices->count() > 0 ? round($netInvoicedTotal / $invoices->count(), 2) : 0.00,
                    'average_revenue_per_day'   => $daysCount > 0 ? round($netRealizedRevenue / $daysCount, 2) : 0.00,
                    'profit_per_day'            => $daysCount > 0 ? round($netOperatingProfit / $daysCount, 2) : 0.00,
                ],
            ])
            ->build();
    }

    /**
     * سجل الحركات المالية التفصيلي (كل حركة بالتفصيل)
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
                'id'               => 'TRX-' . $t->id,
                'timestamp'        => $t->created_at->toIso8601String(),
                'date'             => $t->created_at->format('Y-m-d H:i'),
                'category'         => $isIncome ? 'مقبوضات فواتير' : 'مرتجع فاتورة',
                'payment_method'   => $t->payment_method?->value ?? (string) $t->payment_method,
                'debit'            => $isIncome ? $amount : 0.00,
                'credit'           => !$isIncome ? $amount : 0.00,
                'net_flow'         => $isIncome ? $amount : -$amount,
                'description'      => $t->description ?? ($isIncome ? 'تحصيل فاتورة' : 'استرداد نقدي'),
                'user_name'        => $t->creator?->name ?? 'غير محدد',
                'patient_name'     => $t->invoice?->patient?->name ?? null,
            ]);
        }

        // 2. المصروفات التشغيلية
        $expQuery = Expense::with(['creator', 'shift.user'])
            ->whereBetween('expense_date', [$formattedStart, $formattedEnd]);

        foreach ($expQuery->get() as $e) {
            $amount = (float) $e->amount;
            $movements->push([
                'id'               => 'EXP-' . $e->id,
                'timestamp'        => Carbon::parse($e->expense_date)->startOfDay()->toIso8601String(),
                'date'             => $e->expense_date,
                'category'         => 'مصروفات تشغيلية',
                'payment_method'   => 'cash',
                'debit'            => 0.00,
                'credit'           => $amount,
                'net_flow'         => -$amount,
                'description'      => "مصروف ({$e->category?->value}): {$e->notes}",
                'user_name'        => $e->creator?->name ?? 'غير محدد',
                'patient_name'     => null,
            ]);
        }

        // 3. مسيرات الرواتب المنصرفة
        $payQuery = Payroll::with(['user'])
            ->where('status', PayrollStatusEnum::PAID)
            ->where(function ($q) use ($startDate, $endDate, $formattedStart, $formattedEnd) {
                $q->whereBetween('paid_at', [$startDate, $endDate])
                  ->orWhereBetween('start_date', [$formattedStart, $formattedEnd])
                  ->orWhereBetween('end_date', [$formattedStart, $formattedEnd]);
            });

        foreach ($payQuery->get() as $p) {
            $amount = (float) $p->net_salary;
            $payDate = $p->paid_at ? Carbon::parse($p->paid_at) : Carbon::parse($p->end_date);
            $movements->push([
                'id'               => 'PAY-' . $p->id,
                'timestamp'        => $payDate->toIso8601String(),
                'date'             => $payDate->format('Y-m-d'),
                'category'         => 'رواتب وأجور',
                'payment_method'   => 'cash',
                'debit'            => 0.00,
                'credit'           => $amount,
                'net_flow'         => -$amount,
                'description'      => "صرف راتب شهر ({$p->month}) للموظف {$p->user?->name}",
                'user_name'        => 'الإدارة',
                'patient_name'     => null,
            ]);
        }

        // ترتيب الحركات وحساب الرصيد التراكمي
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
