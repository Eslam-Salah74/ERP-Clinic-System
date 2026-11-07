<?php

namespace Modules\Reception\Services\Report;

use App\Enums\UserType;
use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Models\Expense;
use Modules\HR\Models\Payroll;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\PurchaseInvoiceItem;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Models\Appointment;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\InvoiceItem;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Service;

class DashboardHomeReportService
{
    /**
     * تقرير لوحة تحكم الصفحة الرئيسية (Home Dashboard Summary) بالفلاتر السريعة ومؤشرات الأداء
     */
    public function getHomeSummary($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $deptId    = $request->input('department_id');
        $doctorId  = $request->input('doctor_id');

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd   = $endDate->format('Y-m-d');

        // 1. الفواتير في الفترة المحددة
        $invoicesQuery = Invoice::with(['items.service.items', 'patient', 'doctor.activeContract.serviceCommissions', 'nurse.activeContract', 'transactions'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value);

        if ($deptId) {
            $invoicesQuery->whereHas('items.service', fn($q) => $q->where('department_id', $deptId));
        }

        if ($doctorId) {
            $invoicesQuery->where('doctor_id', $doctorId);
        }

        $invoices = $invoicesQuery->get();

        // 2. السندات المالية (المقبوضات والمرتجعات)
        $transactions = Transaction::whereBetween('created_at', [$startDate, $endDate])->get();

        // 3. المصروفات التشغيلية
        $expenses = Expense::whereBetween('expense_date', [$formattedStart, $formattedEnd])->get();

        // 4. الرواتب المنصرفة
        $payrolls = Payroll::where('status', PayrollStatusEnum::PAID)
            ->where(function ($q) use ($startDate, $endDate, $formattedStart, $formattedEnd) {
                $q->whereBetween('paid_at', [$startDate, $endDate])
                  ->orWhereBetween('start_date', [$formattedStart, $formattedEnd])
                  ->orWhereBetween('end_date', [$formattedStart, $formattedEnd]);
            })->get();

        // 5. فواتير الشراء
        $purchasesTotal = (float) PurchaseInvoice::whereBetween('created_at', [$startDate, $endDate])->sum('total_amount');
        $purchasesCount = PurchaseInvoice::whereBetween('created_at', [$startDate, $endDate])->count();

        // تكاليف الأصناف من أحدث فواتير شراء
        $itemsCosts = $this->loadItemsCosts();

        // --- أ. تفصيل الإيرادات ---
        $grossInvoicedTotal = (float) $invoices->sum('sub_total');
        $totalDiscounts     = (float) $invoices->sum('discount');
        $netInvoicedTotal   = (float) $invoices->sum('grand_total');
        $totalPaidCollected = (float) $invoices->sum('paid_amount');
        $totalRemainingDebt = (float) $invoices->sum('remaining_amount');
        $totalRefunds       = (float) $transactions->where('type', TransactionTypeEnum::REFUND)->sum('amount');
        $netRealizedRevenue = max(0, $netInvoicedTotal - $totalRefunds);

        // تصنيف الإيرادات وحساب المستهلكات المخزنية والعمولات
        $consultationsRev = 0.00;
        $servicesRev      = 0.00;
        $devicesRev       = 0.00;
        $directSalesRev   = 0.00;
        $totalConsumablesCost = 0.00;
        $totalDoctorCommissions = 0.00;

        // مصفوفات تجميعية للأجهزة والأطباء
        $devicesAgg = [];
        $doctorsAgg = [];

        foreach ($invoices as $inv) {
            $doc = $inv->doctor;
            if ($doc && !isset($doctorsAgg[$doc->id])) {
                $doctorsAgg[$doc->id] = [
                    'doctor_id'       => $doc->id,
                    'doctor_name'     => $doc->name,
                    'department_name' => $doc->department?->name ?? 'عام',
                    'patients_count'  => 0,
                    'gross_revenue'   => 0.00,
                    'commissions'     => 0.00,
                ];
            }

            foreach ($inv->items as $item) {
                $itemTotal = (float) $item->total_price;
                $itemQty   = (int) $item->quantity;

                // عمولة الطبيب على البند إن وجدت
                $itemComm = 0.00;
                if ($doc && $doc->activeContract) {
                    $itemComm = $this->calculateDoctorCommissionForItem($item, $doc->activeContract, (bool) ($doc->achieved_target ?? false));
                    $totalDoctorCommissions += $itemComm;

                    if (isset($doctorsAgg[$doc->id])) {
                        $doctorsAgg[$doc->id]['gross_revenue'] += $itemTotal;
                        $doctorsAgg[$doc->id]['commissions']   += $itemComm;
                    }
                }

                if ($item->item_type === 'product') {
                    $directSalesRev += $itemTotal;
                    $unitCost = $itemsCosts[$item->product_id] ?? 0.00;
                    $totalConsumablesCost += ($unitCost * $itemQty);
                } elseif ($item->service) {
                    $svc = $item->service;

                    // تجميع الأجهزة
                    if ($svc->type === ServiceTypeEnum::DEVICE || $svc->items->isNotEmpty()) {
                        $devicesRev += $itemTotal;

                        if (!isset($devicesAgg[$svc->id])) {
                            $devicesAgg[$svc->id] = [
                                'device_id'         => $svc->id,
                                'device_name'       => $svc->name,
                                'department_name'   => $svc->department?->name ?? 'عام',
                                'sessions_count'    => 0,
                                'gross_revenue'     => 0.00,
                                'consumables_cost'  => 0.00,
                                'commissions_paid'  => 0.00,
                            ];
                        }
                        $devicesAgg[$svc->id]['sessions_count'] += $itemQty;
                        $devicesAgg[$svc->id]['gross_revenue']  += $itemTotal;
                        $devicesAgg[$svc->id]['commissions_paid'] += $itemComm;
                    } elseif ($svc->type === ServiceTypeEnum::CONSULTATION) {
                        $consultationsRev += $itemTotal;
                    } else {
                        $servicesRev += $itemTotal;
                    }

                    // حساب تكلفة مستهلكات الخدمة
                    if ($svc->items) {
                        foreach ($svc->items as $sItem) {
                            $qtyPerSvc = (float) ($sItem->pivot->quantity ?? 1);
                            $uCost = (float) ($sItem->pivot->price > 0 ? $sItem->pivot->price : ($itemsCosts[$sItem->id] ?? 0));
                            $itemCostTotal = ($qtyPerSvc * $itemQty * $uCost);
                            $totalConsumablesCost += $itemCostTotal;

                            if (isset($devicesAgg[$svc->id])) {
                                $devicesAgg[$svc->id]['consumables_cost'] += $itemCostTotal;
                            }
                        }
                    }
                }
            }

            if ($doc && isset($doctorsAgg[$doc->id])) {
                $doctorsAgg[$doc->id]['patients_count']++;
            }
        }

        // --- ب. التكاليف والمصروفات والأرباح ---
        $totalOperatingExpenses = (float) $expenses->sum('amount');
        $totalSalariesPaid      = (float) $payrolls->sum('net_salary');
        $totalDirectCostCOGS    = round($totalConsumablesCost, 2);
        $totalSalariesAndComms  = round($totalSalariesPaid + $totalDoctorCommissions, 2);

        $grossProfit = round($netRealizedRevenue - $totalDirectCostCOGS, 2);
        $netOperatingProfit = round($grossProfit - $totalOperatingExpenses - $totalSalariesPaid, 2);
        $netProfitMargin = ($netRealizedRevenue > 0) ? round(($netOperatingProfit / $netRealizedRevenue) * 100, 2) : 0.00;

        $totalCashOutflows = round($totalOperatingExpenses + $totalSalariesPaid, 2);

        // --- ج. أعلى 5 أجهزة ربحية ---
        foreach ($devicesAgg as &$dev) {
            $dev['gross_revenue']    = round($dev['gross_revenue'], 2);
            $dev['consumables_cost'] = round($dev['consumables_cost'], 2);
            $dev['commissions_paid'] = round($dev['commissions_paid'], 2);
            $dev['net_profit']       = round($dev['gross_revenue'] - $dev['consumables_cost'] - $dev['commissions_paid'], 2);
            $dev['profit_margin']    = $dev['gross_revenue'] > 0 ? round(($dev['net_profit'] / $dev['gross_revenue']) * 100, 2) : 0.00;
        }
        unset($dev);
        usort($devicesAgg, fn($a, $b) => $b['net_profit'] <=> $a['net_profit']);
        $topDevices = array_slice($devicesAgg, 0, 5);

        // --- د. أعلى 5 أطباء تحقيقاً للدخل ---
        foreach ($doctorsAgg as &$docData) {
            $docData['gross_revenue']   = round($docData['gross_revenue'], 2);
            $docData['commissions']     = round($docData['commissions'], 2);
            $docData['clinic_net_share']= round(max(0, $docData['gross_revenue'] - $docData['commissions']), 2);
        }
        unset($docData);
        usort($doctorsAgg, fn($a, $b) => $b['gross_revenue'] <=> $a['gross_revenue']);
        $topDoctors = array_slice($doctorsAgg, 0, 5);

        // --- هـ. تقييم المخزون المتبقي حالياً ---
        $allItems = Item::where('is_active', true)->get();
        $currentTotalStockAssetValue = 0.00;
        foreach ($allItems as $itm) {
            $cost = $itemsCosts[$itm->id] ?? (float) $itm->selling_price;
            $currentTotalStockAssetValue += ((float) $itm->current_stock * $cost);
        }
        $currentTotalStockAssetValue = round($currentTotalStockAssetValue, 2);
        $unconsumedGain = max(0, $purchasesTotal - $totalDirectCostCOGS);

        // --- و. نشاط اليوم الحالي السريع (Today's Pulse) ---
        $todayDate = Carbon::today()->toDateString();
        $todayAppointmentsCount = Appointment::whereDate('appointment_date', $todayDate)->count();
        $todayInvoices = Invoice::whereDate('created_at', $todayDate)->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)->get();
        $todayRevenue = (float) $todayInvoices->sum('grand_total');
        $openShiftsCount = Shift::where('status', ShiftStatusEnum::OPEN->value)->count();

        return API::newInstance()
            ->isOk('تم جلب ملخص لوحة تحكم الصفحة الرئيسية بنجاح')
            ->setData([
                'dashboard_title'        => 'لوحة التحكم الرئيسية للمركز الطبي (Executive Dashboard)',
                'applied_filters'        => [
                    'preset'     => $request->input('period_preset', 'this_month'),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $startDate->diffInDays($endDate) + 1,
                    'department_id' => $deptId,
                    'doctor_id'     => $doctorId,
                ],

                // 1. كروت المؤشرات الستة الأساسية للمدير
                'executive_kpi_cards'    => [
                    'net_profit'                 => $netOperatingProfit,              // كسب كام (صافي الربح)
                    'performance_status'         => $netOperatingProfit >= 0 ? 'profit' : 'loss',
                    'net_profit_margin_percent'  => $netProfitMargin,                 // نسبة الهامش
                    'gross_realized_revenue'     => round($netRealizedRevenue, 2),     // إجمالي المبيعات
                    'total_cash_outflows'        => $totalCashOutflows,               // دفع كام كاش
                    'inventory_consumables_cogs' => $totalDirectCostCOGS,             // الاستهلاك المخزني
                    'operating_expenses'         => round($totalOperatingExpenses, 2), // المصروفات
                    'salaries_and_commissions'   => $totalSalariesAndComms,            // الرواتب والعمولات
                ],

                // 2. صندوق إيضاح مشتريات المخزون لعدة شهور
                'inventory_multi_month_clarity' => [
                    'purchases_during_period'    => round($purchasesTotal, 2),
                    'purchases_invoices_count'   => $purchasesCount,
                    'consumed_in_sessions_cogs'  => $totalDirectCostCOGS,
                    'unconsumed_inventory_asset' => round($unconsumedGain, 2),
                    'current_total_stock_value'  => $currentTotalStockAssetValue,
                    'is_deficit'                 => false,
                    'clarification'              => 'شراء بضاعة تكفي فترات مستقبلية لا يُعد عجزاً ولا خسارة؛ بل هو أصل متداول بالمخزن، ويُخصم من ربح الفترة فقط ما تم استهلاكه فعلياً بالجلسات.',
                ],

                // 3. مؤشرات الخزينة والتحصيل
                'collections_and_safe'   => [
                    'total_collected'            => round($totalPaidCollected, 2),
                    'remaining_uncollected_debt' => round($totalRemainingDebt, 2),
                    'collection_rate_percent'    => $netInvoicedTotal > 0 ? round(($totalPaidCollected / $netInvoicedTotal) * 100, 2) : 0.00,
                ],

                // 4. توزيع الإيرادات حسب الأقسام ونوع الخدمات
                'revenue_by_category'    => [
                    'consultations' => round($consultationsRev, 2),
                    'services'      => round($servicesRev, 2),
                    'devices'       => round($devicesRev, 2),
                    'direct_sales'  => round($directSalesRev, 2),
                ],

                // 5. أعلى 5 أجهزة ربحية
                'top_profitable_devices' => $topDevices,

                // 6. أعلى 5 أطباء تحقيقاً للدخل
                'top_performing_doctors' => $topDoctors,

                // 7. نشاط اليوم الحالي السريع
                'today_quick_pulse'      => [
                    'today_date'                => $todayDate,
                    'today_appointments_count'  => $todayAppointmentsCount,
                    'today_invoices_count'      => $todayInvoices->count(),
                    'today_revenue'             => round($todayRevenue, 2),
                    'active_open_shifts_count'  => $openShiftsCount,
                ],
            ])
            ->build();
    }

    /**
     * حساب عمولة الطبيب على البند المحدد
     */
    protected function calculateDoctorCommissionForItem(InvoiceItem $item, $contract, bool $isPermanentTarget): float
    {
        $service = $item->service;
        if (!$service || !$contract) {
            return 0.00;
        }

        $itemTotal = (float) $item->total_price;
        $qty       = (int) $item->quantity;

        $specificComm = $contract->serviceCommissions?->firstWhere('service_id', $service->id);
        if ($specificComm) {
            $isFixedAmount = ($specificComm->commission_type === \Modules\HR\Enums\CommissionTypeEnum::FIXED || $specificComm->commission_type === \Modules\HR\Enums\CommissionTypeEnum::FIXED->value);

            if ($isFixedAmount) {
                return round((float) $specificComm->commission_value * $qty, 2);
            }

            $basePct = (float) $specificComm->commission_value;
            $appliedPct = $basePct;

            if ($isPermanentTarget && $contract->has_target) {
                if ($specificComm->target_commission_value !== null && (float) $specificComm->target_commission_value > 0) {
                    $appliedPct = (float) $specificComm->target_commission_value;
                }
            }

            return round($itemTotal * ($appliedPct / 100), 2);
        }

        $otherPct = (float) ($contract->other_service_commission_percentage ?? 0);
        if ($otherPct <= 0 && (float) ($contract->default_service_commission_value ?? 0) > 0) {
            $otherPct = (float) $contract->default_service_commission_value;
        }

        if ($otherPct > 0) {
            return round($itemTotal * ($otherPct / 100), 2);
        }

        return 0.00;
    }

    /**
     * تحميل متوسط أو أحدث أسعار الشراء للأصناف المخزنية
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

