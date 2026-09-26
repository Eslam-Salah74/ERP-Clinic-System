<?php

namespace Modules\Reception\Services\Report;

use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\HR\Models\Expense;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Setup\Enums\ServiceTypeEnum;

class ShiftSafeReportService
{
    /**
     * توليد تقرير الخزنة اليومية للموظف / الشفت
     */
    public function getDailySafeReport($request)
    {
        $shiftId   = $request->input('shift_id');
        $userId    = $request->input('user_id');
        $date      = $request->input('date');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        // تحديد الشفتات المستهدفة
        $shiftsQuery = Shift::with(['user.role']);

        if ($shiftId) {
            $shiftsQuery->where('id', $shiftId);
        } else {
            if ($userId) {
                $shiftsQuery->where('user_id', $userId);
            }

            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end   = Carbon::parse($endDate)->endOfDay();
                $shiftsQuery->whereBetween('start_time', [$start, $end]);
            } elseif ($date) {
                $targetDate = Carbon::parse($date);
                $shiftsQuery->whereDate('start_time', $targetDate);
            } else {
                // الافتراضي: إذا لم يُحدد شيء، نبحث عن الشفت المفتوح للمستخدم الحالي أو شفتات اليوم
                $currentActive = Shift::where('user_id', Auth::id())
                    ->where('status', ShiftStatusEnum::OPEN->value)
                    ->first();

                if ($currentActive && !$userId) {
                    $shiftsQuery->where('id', $currentActive->id);
                } else {
                    $shiftsQuery->whereDate('start_time', Carbon::today());
                }
            }
        }

        $shifts = $shiftsQuery->orderBy('start_time', 'desc')->get();

        if ($shifts->isEmpty()) {
            return API::newInstance()
                ->isOk('لا توجد شفتات مسجلة تطابق محددات البحث المرفقة.')
                ->setData([
                    'shifts_count'      => 0,
                    'summary'           => null,
                    'shifts'            => [],
                    'detailed_movements'=> [],
                ])
                ->build();
        }

        $shiftIds = $shifts->pluck('id')->toArray();

        // 1. تجميع الفواتير المرتبطة بهذه الشفتات
        $invoices = Invoice::with(['patient', 'doctor', 'items.service'])
            ->whereIn('shift_id', $shiftIds)
            ->get();

        // 2. تجميع السندات المالية (المقبوضات والمرتجعات)
        $transactions = Transaction::with(['invoice.patient', 'creator'])
            ->whereIn('shift_id', $shiftIds)
            ->get();

        // 3. تجميع المصروفات النقدية المنصرفة من الخزينة/الشفت
        $expenses = Expense::with(['creator'])
            ->whereIn('shift_id', $shiftIds)
            ->get();

        // حساب الإجماليات المالية
        $totalInitialBalance = (float) $shifts->sum('initial_balance');
        $totalFinalBalance   = (float) $shifts->sum('final_balance');

        // المقبوضات حسب وسيلة الدفع
        $cashInflows = (float) $transactions
            ->where('payment_method', PaymentMethodEnum::CASH)
            ->where('type', TransactionTypeEnum::INCOME)
            ->sum('amount');

        $visaInflows = (float) $transactions
            ->where('payment_method', PaymentMethodEnum::VISA)
            ->where('type', TransactionTypeEnum::INCOME)
            ->sum('amount');

        $walletInflows = (float) $transactions
            ->where('payment_method', PaymentMethodEnum::WALLET)
            ->where('type', TransactionTypeEnum::INCOME)
            ->sum('amount');

        $insuranceInflows = (float) $transactions
            ->where('payment_method', PaymentMethodEnum::INSURANCE)
            ->where('type', TransactionTypeEnum::INCOME)
            ->sum('amount');

        $totalRevenueInflows = $cashInflows + $visaInflows + $walletInflows + $insuranceInflows;

        // المدفوعات والمنصرفات من الدرج (المصروفات النقدية + المرتجعات النقدية)
        $cashExpenses = (float) $expenses
            ->filter(fn($e) => $e->payment_method === PaymentMethodEnum::CASH || $e->payment_method === null)
            ->sum('amount');

        $nonCashExpenses = (float) $expenses
            ->filter(fn($e) => $e->payment_method !== null && $e->payment_method !== PaymentMethodEnum::CASH)
            ->sum('amount');

        $cashRefunds = (float) $transactions
            ->where('payment_method', PaymentMethodEnum::CASH)
            ->where('type', TransactionTypeEnum::REFUND)
            ->sum('amount');

        $nonCashRefunds = (float) $transactions
            ->where('payment_method', '!=', PaymentMethodEnum::CASH)
            ->where('type', TransactionTypeEnum::REFUND)
            ->sum('amount');

        $totalCashOutflows = $cashExpenses + $cashRefunds;

        // حساب الرصيد المتوقع والعجز والزيادة
        $expectedCashBalance = ($totalInitialBalance + $cashInflows) - $totalCashOutflows;
        $hasClosedShifts     = $shifts->contains(fn($s) => $s->status === ShiftStatusEnum::CLOSED);
        $totalDifference     = $hasClosedShifts ? round($totalFinalBalance - $expectedCashBalance, 2) : 0.00;

        $statusNote = 'قيد العمل (شفت مفتوح)';
        if ($hasClosedShifts) {
            if ($totalDifference == 0) {
                $statusNote = 'الخزينة مضبوطة تماماً بدون أي عجز أو زيادة';
            } elseif ($totalDifference > 0) {
                $statusNote = "يوجد زيادة في الدرج بقيمة {$totalDifference} ج";
            } else {
                $statusNote = "يوجد عجز في الدرج بقيمة " . abs($totalDifference) . " ج";
            }
        }

        // إحصائيات العمليات
        $consultationsCount = 0;
        $servicesCount      = 0;
        $devicesCount       = 0;
        $directSalesCount   = 0;

        foreach ($invoices as $inv) {
            foreach ($inv->items as $item) {
                if ($item->item_type === 'product') {
                    $directSalesCount += (int) $item->quantity;
                } elseif ($item->service) {
                    if ($item->service->type === ServiceTypeEnum::CONSULTATION) {
                        $consultationsCount += (int) $item->quantity;
                    } elseif ($item->service->type === ServiceTypeEnum::DEVICE) {
                        $devicesCount += (int) $item->quantity;
                    } else {
                        $servicesCount += (int) $item->quantity;
                    }
                }
            }
        }

        // بناء جدول الحركات التفصيلية للشفت / الخزينة
        $movements = collect();

        // 1. حركة الرصيد الافتتاحي
        foreach ($shifts as $s) {
            $movements->push([
                'timestamp'        => $s->start_time ? $s->start_time->format('Y-m-d H:i:s') : null,
                'time_formatted'   => $s->start_time ? $s->start_time->format('h:i A') : null,
                'shift_id'         => $s->id,
                'cashier_name'     => $s->user?->name ?? 'غير محدد',
                'movement_type'    => 'opening_balance',
                'type_arabic'      => 'رصيد افتتاحي للدرج',
                'ref_number'       => "SHIFT-{$s->id}",
                'patient_name'     => '-',
                'doctor_name'      => '-',
                'payment_method'   => 'كاش',
                'inflow'           => (float) $s->initial_balance,
                'outflow'          => 0.00,
                'amount'           => (float) $s->initial_balance,
                'notes'            => 'بداية الشفت واستلام العهدة الافتتاحية',
            ]);
        }

        // 2. حركات المقبوضات والمرتجعات (Transactions)
        foreach ($transactions as $t) {
            $isIncome = ($t->type === TransactionTypeEnum::INCOME);
            $movements->push([
                'timestamp'        => $t->created_at ? $t->created_at->format('Y-m-d H:i:s') : null,
                'time_formatted'   => $t->created_at ? $t->created_at->format('h:i A') : null,
                'shift_id'         => $t->shift_id,
                'cashier_name'     => $t->creator?->name ?? 'غير محدد',
                'movement_type'    => $isIncome ? 'income' : 'refund',
                'type_arabic'      => $isIncome ? 'سند تحصيل فاتورة' : 'سند مرتجع نقدي',
                'ref_number'       => $t->transaction_number ?? ($t->invoice?->invoice_number ?? "TRX-{$t->id}"),
                'patient_name'     => $t->invoice?->patient?->name ?? '-',
                'doctor_name'      => $t->invoice?->doctor?->name ?? '-',
                'payment_method'   => $t->payment_method?->value ?? 'cash',
                'inflow'           => $isIncome ? (float) $t->amount : 0.00,
                'outflow'          => !$isIncome ? (float) $t->amount : 0.00,
                'amount'           => (float) $t->amount,
                'notes'            => $t->description ?? ($isIncome ? 'سداد فاتورة مريض' : 'مرتجع للمريض'),
            ]);
        }

        // 3. حركات المصروفات النقدية
        foreach ($expenses as $exp) {
            $movements->push([
                'timestamp'        => $exp->created_at ? $exp->created_at->format('Y-m-d H:i:s') : null,
                'time_formatted'   => $exp->created_at ? $exp->created_at->format('h:i A') : null,
                'shift_id'         => $exp->shift_id,
                'cashier_name'     => $exp->creator?->name ?? 'غير محدد',
                'movement_type'    => 'expense',
                'type_arabic'      => 'مصروف من الخزينة (' . ($exp->category?->value ?? 'عام') . ')',
                'ref_number'       => "EXP-{$exp->id}",
                'patient_name'     => '-',
                'doctor_name'      => '-',
                'payment_method'   => $exp->payment_method?->value ?? 'cash',
                'inflow'           => 0.00,
                'outflow'          => (float) $exp->amount,
                'amount'           => (float) $exp->amount,
                'notes'            => $exp->notes ?? 'مصروف نثريات/تشغيل خلال الوردية',
            ]);
        }

        // ترتيب الحركات زمنياً
        $sortedMovements = $movements->sortBy('timestamp')->values();

        // تفاصيل كل شفت على حدة
        $shiftsDetails = $shifts->map(function ($s) use ($transactions, $expenses) {
            $shiftTrx = $transactions->where('shift_id', $s->id);
            $shiftExp = $expenses->where('shift_id', $s->id);

            $sCashIncome = (float) $shiftTrx->where('payment_method', PaymentMethodEnum::CASH)->where('type', TransactionTypeEnum::INCOME)->sum('amount');
            $sVisaIncome = (float) $shiftTrx->where('payment_method', PaymentMethodEnum::VISA)->where('type', TransactionTypeEnum::INCOME)->sum('amount');
            $sWalletIncome = (float) $shiftTrx->where('payment_method', PaymentMethodEnum::WALLET)->where('type', TransactionTypeEnum::INCOME)->sum('amount');
            $sInsuranceIncome = (float) $shiftTrx->where('payment_method', PaymentMethodEnum::INSURANCE)->where('type', TransactionTypeEnum::INCOME)->sum('amount');

            $sCashExpenses = (float) $shiftExp->filter(fn($e) => $e->payment_method === PaymentMethodEnum::CASH || $e->payment_method === null)->sum('amount');
            $sCashRefunds  = (float) $shiftTrx->where('payment_method', PaymentMethodEnum::CASH)->where('type', TransactionTypeEnum::REFUND)->sum('amount');

            $sExpectedCash = ((float) $s->initial_balance + $sCashIncome) - ($sCashExpenses + $sCashRefunds);
            $sActualCash   = (float) $s->final_balance;
            $sDiff         = ($s->status === ShiftStatusEnum::CLOSED) ? round($sActualCash - $sExpectedCash, 2) : 0.00;

            return [
                'shift_id'           => $s->id,
                'employee_id'        => $s->user_id,
                'employee_name'      => $s->user?->name ?? 'غير معروف',
                'employee_phone'     => $s->user?->phone,
                'status'             => $s->status?->value ?? 'closed',
                'start_time'         => $s->start_time ? $s->start_time->format('Y-m-d H:i:s') : null,
                'end_time'           => $s->end_time ? $s->end_time->format('Y-m-d H:i:s') : null,
                'duration_hours'     => $s->start_time && $s->end_time ? round($s->start_time->diffInMinutes($s->end_time) / 60, 2) : null,
                'is_late'            => (bool) $s->is_late,
                'late_minutes'       => (int) $s->late_minutes,
                'overtime_minutes'   => (int) $s->overtime_minutes,
                'opening_latitude'   => $s->opening_latitude,
                'opening_longitude'  => $s->opening_longitude,
                'initial_balance'    => (float) $s->initial_balance,
                'cash_collected'     => $sCashIncome,
                'visa_collected'     => $sVisaIncome,
                'wallet_collected'   => $sWalletIncome,
                'insurance_collected'=> $sInsuranceIncome,
                'total_revenue'      => $sCashIncome + $sVisaIncome + $sWalletIncome + $sInsuranceIncome,
                'cash_expenses_paid' => $sCashExpenses,
                'cash_refunds_paid'  => $sCashRefunds,
                'total_cash_outflow' => $sCashExpenses + $sCashRefunds,
                'expected_cash'      => round($sExpectedCash, 2),
                'actual_cash'        => $sActualCash,
                'difference'         => $sDiff,
                'difference_status'  => $sDiff == 0 ? 'balanced' : ($sDiff > 0 ? 'surplus' : 'shortage'),
                'difference_note'    => $sDiff == 0 ? 'مضبوط' : ($sDiff > 0 ? "+{$sDiff} ج زيادة" : "{$sDiff} ج عجز"),
            ];
        });

        return API::newInstance()
            ->isOk('تم توليد تقرير الخزنة اليومية بنجاح')
            ->setData([
                'report_title'           => 'تقرير الخزنة اليومية ومطابقة النقدية',
                'report_date'            => Carbon::now()->format('Y-m-d H:i:s'),
                'shifts_count'           => $shifts->count(),
                'treasury_summary'       => [
                    'opening_balance'         => round($totalInitialBalance, 2),
                    'cash_collected'          => round($cashInflows, 2),
                    'visa_collected'          => round($visaInflows, 2),
                    'wallet_collected'        => round($walletInflows, 2),
                    'insurance_collected'     => round($insuranceInflows, 2),
                    'total_collected_revenue' => round($totalRevenueInflows, 2),
                    'cash_expenses_paid'      => round($cashExpenses, 2),
                    'non_cash_expenses'       => round($nonCashExpenses, 2),
                    'cash_refunds_paid'       => round($cashRefunds, 2),
                    'non_cash_refunds'        => round($nonCashRefunds, 2),
                    'total_cash_outflows'     => round($totalCashOutflows, 2),
                    'expected_drawer_balance' => round($expectedCashBalance, 2),
                    'actual_drawer_balance'   => round($totalFinalBalance, 2),
                    'difference'              => round($totalDifference, 2),
                    'difference_status'       => $totalDifference == 0 ? 'balanced' : ($totalDifference > 0 ? 'surplus' : 'shortage'),
                    'status_note'             => $statusNote,
                ],
                'operations_breakdown'   => [
                    'total_invoices_issued' => $invoices->count(),
                    'consultations_count'   => $consultationsCount,
                    'services_count'        => $servicesCount,
                    'devices_count'         => $devicesCount,
                    'direct_sales_count'    => $directSalesCount,
                    'unique_patients_count' => $invoices->pluck('patient_id')->filter()->unique()->count(),
                    'expenses_count'        => $expenses->count(),
                    'refunds_count'         => $transactions->where('type', TransactionTypeEnum::REFUND)->count(),
                ],
                'shifts'                 => $shiftsDetails,
                'detailed_movements'     => $sortedMovements,
            ])
            ->build();
    }
}
