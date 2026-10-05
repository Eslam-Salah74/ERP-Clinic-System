<?php

namespace Modules\HR\Services\Payroll;

use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\HR\Enums\ContractTypeEnum;
use Modules\HR\Enums\PayrollStatusEnum;
use Modules\HR\Enums\TargetTypeEnum;
use Modules\HR\Filters\Payroll\PayrollFilter;
use Modules\HR\Http\Requests\Payroll\GeneratePayrollRequest;
use Modules\HR\Http\Requests\Payroll\PayPayrollRequest;
use Modules\HR\Http\Requests\Payroll\UpdatePayrollRequest;
use Modules\HR\Http\Resources\Payroll\PayrollResource;
use Modules\HR\Models\Payroll;
use Modules\HR\Models\PayrollItem;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\InvoiceItem;
use Modules\Reception\Models\Shift;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;
use Modules\Setup\Models\Service;
use Modules\HR\Models\ContractServiceCommission;

class PayrollService
{
    public function index($request, PayrollFilter $filter)
    {
        $data = Payroll::with(['user', 'contract', 'approver', 'payer'])
            ->filter($filter)
            ->latest('id')
            ->paginate(15);

        return API::newInstance()
            ->isOk('Payrolls retrieved successfully')
            ->setData(PayrollResource::collection($data))
            ->build();
    }

    public function show($id)
    {
        $payroll = Payroll::with(['user', 'contract', 'items', 'approver', 'payer'])->find($id);
        if (!$payroll) {
            return API::newInstance()->isError('Payroll record not found')->build();
        }

        return API::newInstance()
            ->isOk('Payroll retrieved successfully')
            ->setData(new PayrollResource($payroll))
            ->build();
    }

    /**
     * توليد مسير الراتب لموظف محدد عن فترة محددة أو شهر معين
     */
    public function generate(GeneratePayrollRequest $request)
    {
        $validated = $request->validated();
        $userId = (int) $validated['user_id'];

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();
            $month = $validated['month'] ?? $startDate->format('Y-m');
        } else {
            $month = $validated['month'];
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->endOfDay();
        }

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd = $endDate->format('Y-m-d');

        // التحقق من عدم وجود مسير راتب تم صرفه بالفعل ومتداخل مع هذه الفترة
        $overlappingPaid = Payroll::where('user_id', $userId)
            ->where('status', PayrollStatusEnum::PAID)
            ->where(function ($query) use ($formattedStart, $formattedEnd) {
                $query->whereBetween('start_date', [$formattedStart, $formattedEnd])
                    ->orWhereBetween('end_date', [$formattedStart, $formattedEnd])
                    ->orWhere(function ($q) use ($formattedStart, $formattedEnd) {
                        $q->where('start_date', '<=', $formattedStart)
                          ->where('end_date', '>=', $formattedEnd);
                    });
            })
            ->first();

        if ($overlappingPaid) {
            $paidStart = $overlappingPaid->start_date ? Carbon::parse($overlappingPaid->start_date)->format('Y-m-d') : $overlappingPaid->month;
            $paidEnd = $overlappingPaid->end_date ? Carbon::parse($overlappingPaid->end_date)->format('Y-m-d') : $overlappingPaid->month;
            return API::newInstance()->isError("مسير الراتب لهذا الموظف عن فترة متداخلة (من {$paidStart} إلى {$paidEnd}) تم صرفه بالفعل ولا يمكن إعادة توليده")->build();
        }

        $payroll = $this->calculateAndSavePayroll($userId, $month, $startDate, $endDate, $validated);

        return API::newInstance()
            ->isCreated('تم احتساب مسير الراتب بنجاح')
            ->setData(new PayrollResource($payroll->load(['user', 'contract', 'items'])))
            ->build();
    }

    /**
     * توليد مسيرات الرواتب لكافة الموظفين النشطين عن شهر أو فترة محددة دفعة واحدة
     */
    public function generateAll($params)
    {
        if (is_array($params)) {
            if (!empty($params['start_date']) && !empty($params['end_date'])) {
                $startDate = Carbon::parse($params['start_date'])->startOfDay();
                $endDate = Carbon::parse($params['end_date'])->endOfDay();
                $month = $params['month'] ?? $startDate->format('Y-m');
            } else {
                $month = $params['month'];
                $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
                $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->endOfDay();
            }
        } else {
            $month = (string) $params;
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->endOfDay();
        }

        $formattedStart = $startDate->format('Y-m-d');
        $formattedEnd = $endDate->format('Y-m-d');

        $users = User::where('is_active', true)->get();
        $generatedCount = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                $overlappingPaid = Payroll::where('user_id', $user->id)
                    ->where('status', PayrollStatusEnum::PAID)
                    ->where(function ($query) use ($formattedStart, $formattedEnd) {
                        $query->whereBetween('start_date', [$formattedStart, $formattedEnd])
                            ->orWhereBetween('end_date', [$formattedStart, $formattedEnd])
                            ->orWhere(function ($q) use ($formattedStart, $formattedEnd) {
                                $q->where('start_date', '<=', $formattedStart)
                                  ->where('end_date', '>=', $formattedEnd);
                            });
                    })
                    ->first();

                if ($overlappingPaid) {
                    continue;
                }

                $this->calculateAndSavePayroll($user->id, $month, $startDate, $endDate, []);
                $generatedCount++;
            } catch (\Exception $e) {
                $errors[] = "خطأ للموظف {$user->name}: " . $e->getMessage();
            }
        }

        return API::newInstance()
            ->isOk("تم توليد {$generatedCount} مسير راتب للفترة من {$formattedStart} إلى {$formattedEnd}")
            ->setData([
                'generated_count' => $generatedCount,
                'period' => [
                    'start_date' => $formattedStart,
                    'end_date' => $formattedEnd,
                    'month' => $month,
                ],
                'errors' => $errors,
            ])
            ->build();
    }

    /**
     * المنطق المحاسبي الشامل لاحتساب الراتب والعمولات والخصومات
     */
    public function calculateAndSavePayroll(int $userId, string $month, ?Carbon $startDate = null, ?Carbon $endDate = null, array $extra = []): Payroll
    {
        return DB::transaction(function () use ($userId, $month, $startDate, $endDate, $extra) {
            $user = User::with(['activeContract.serviceCommissions.service'])->findOrFail($userId);
            $contract = $user->activeContract;

            if (!$startDate || !$endDate) {
                $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
                $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->endOfDay();
            }

            $formattedStart = $startDate->format('Y-m-d');
            $formattedEnd = $endDate->format('Y-m-d');

            // 1. الراتب الأساسي (يؤخذ من جدول المستخدمين مع دعم التوزيع النسبي للأيام)
            $userBasicSalary = (float) ($user->basic_salary ?? 0);
            $daysInMonth = $startDate->daysInMonth;
            $periodDays = $startDate->diffInDays($endDate) + 1;

            if (isset($extra['basic_salary']) && is_numeric($extra['basic_salary'])) {
                $basicSalary = (float) $extra['basic_salary'];
            } elseif ($periodDays >= $daysInMonth || $periodDays >= 28) {
                $basicSalary = $userBasicSalary;
            } else {
                $basicSalary = round(($userBasicSalary / $daysInMonth) * $periodDays, 2);
            }

            // 2. إحصائيات الشفتات وساعات العمل والتأخيرات
            $shifts = Shift::where('user_id', $userId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

            $shiftsCount = $shifts->count();
            $totalWorkingHours = 0;
            $overtimeHours = 0;
            $lateMinutes = 0;

            foreach ($shifts as $shift) {
                if ($shift->start_time && $shift->end_time) {
                    $diffHours = abs(Carbon::parse($shift->start_time)->diffInMinutes(Carbon::parse($shift->end_time))) / 60;
                    $totalWorkingHours += $diffHours;
                }

                if ($shift->is_late) {
                    $lateMinutes += (int) $shift->late_minutes;
                }

                if ($shift->overtime_approved) {
                    $overtimeHours += ((int) $shift->overtime_minutes) / 60;
                }
            }

            // إمكانية تمرير ساعات العمل يدوياً (مفيد للدكاترة والموظفين بالساعة بدون شفتات مسجلة)
            $manualHours = $extra['total_working_hours'] ?? $extra['working_hours'] ?? null;
            if ($manualHours !== null && is_numeric($manualHours)) {
                $totalWorkingHours = (float) $manualHours;
            }

            $hourlyRate = (isset($extra['hourly_rate']) && is_numeric($extra['hourly_rate']))
                ? (float) $extra['hourly_rate']
                : (float) ($contract?->hourly_rate ?? 0);

            $hourlyPay = round($totalWorkingHours * $hourlyRate, 2);

            $overtimeHourRate = (float) ($contract?->overtime_hour_rate ?? 0);
            $overtimeAmount = round($overtimeHours * $overtimeHourRate, 2);

            $lateDeductionRate = (float) ($contract?->late_deduction_rate_per_hour ?? 0);
            $lateDeductionAmount = round(($lateMinutes / 60) * $lateDeductionRate, 2);

            $holidayDays = (int) ($extra['holiday_days'] ?? 0);
            $holidayDayRate = (float) ($contract?->holiday_day_rate ?? 0);
            $holidayAllowanceAmount = round($holidayDays * $holidayDayRate, 2);

            $itemsToCreate = [];

            if ($basicSalary > 0) {
                $basicDesc = ($periodDays < 28)
                    ? "الراتب الأساسي النسبي للفترة ({$periodDays} يوم من أصل {$daysInMonth} يوم)"
                    : 'الراتب الأساسي الشهري';

                $itemsToCreate[] = [
                    'type' => 'basic_salary',
                    'description' => $basicDesc,
                    'amount' => $basicSalary,
                    'is_addition' => true,
                ];
            }

            if ($hourlyPay > 0) {
                $itemsToCreate[] = [
                    'type' => 'hourly_pay',
                    'description' => "أجر ساعات العمل الفعلى ({$totalWorkingHours} ساعة × {$hourlyRate} ج)",
                    'amount' => $hourlyPay,
                    'is_addition' => true,
                ];
            }

            if ($overtimeAmount > 0) {
                $itemsToCreate[] = [
                    'type' => 'overtime',
                    'description' => "إضافي ساعات عمل معتمدة ({$overtimeHours} ساعة × {$overtimeHourRate} ج)",
                    'amount' => $overtimeAmount,
                    'is_addition' => true,
                ];
            }

            if ($lateDeductionAmount > 0) {
                $itemsToCreate[] = [
                    'type' => 'late_deduction',
                    'description' => "خصم دقائق تأخير ({$lateMinutes} دقيقة بمعدل {$lateDeductionRate} ج/ساعة)",
                    'amount' => $lateDeductionAmount,
                    'is_addition' => false,
                ];
            }

            if ($holidayAllowanceAmount > 0) {
                $itemsToCreate[] = [
                    'type' => 'holiday_allowance',
                    'description' => "بدل أيام إجازات رسمية ({$holidayDays} يوم × {$holidayDayRate} ج)",
                    'amount' => $holidayAllowanceAmount,
                    'is_addition' => true,
                ];
            }

            // 3. عمولات الأطباء والخدمات
            $servicesCount = 0;
            $serviceCommissionsAmount = 0;
            $clinicRevenueGenerated = 0;

            $doctorInvoices = Invoice::with(['items.service', 'patient'])
                ->where('doctor_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
                ->get();

            $serviceCommissionItems = [];
            $uncontractedServices = [];

            // التحقق من حالة التارجت الدائم للطبيب في ملف المستخدم
            $isPermanentTargetAchieved = (bool) ($user->achieved_target ?? false);

            foreach ($doctorInvoices as $inv) {
                $clinicRevenueGenerated += (float) $inv->grand_total;

                foreach ($inv->items as $item) {
                    if (in_array($item->item_type, ['service', 'package_consumption']) && $item->service) {
                        $service = $item->service;
                        $quantity = (float) $item->quantity;

                        // البحث عن الخدمة في قائمة خدمات عقد الطبيب
                        $specificComm = $contract?->serviceCommissions?->firstWhere('service_id', $service->id);

                        if (!$specificComm) {
                            // الخدمة غير مسجلة بعقد الطبيب: عمولتها 0 ج وتسجل في تقرير تنبيهي للإدارة
                            $uncontractedServices[] = [
                                'service_id' => $service->id,
                                'service_name' => $service->name,
                                'invoice_id' => $inv->id,
                                'invoice_number' => $inv->invoice_number,
                                'patient_name' => $inv->patient?->name,
                                'patient_price' => (float) $item->total_price,
                                'quantity' => $quantity,
                                'date' => $inv->created_at?->format('Y-m-d H:i'),
                            ];

                            $serviceCommissionItems[] = [
                                'type' => 'uncontracted_service',
                                'description' => "خدمة خارج العقد: {$service->name} (كمية {$quantity}) - فاتورة {$inv->invoice_number} (تستوجب مراجعة الإدارة)",
                                'amount' => 0,
                                'is_addition' => true,
                                'reference_id' => $inv->id,
                                'reference_type' => 'invoice',
                                'metadata' => [
                                    'service_id' => $service->id,
                                    'service_name' => $service->name,
                                    'invoice_number' => $inv->invoice_number,
                                    'quantity' => $quantity,
                                    'patient_total_price' => (float) $item->total_price,
                                    'is_uncontracted' => true,
                                    'note' => 'الخدمة غير مدرجة بعقد الطبيب وعمولتها 0 ج وتستوجب مراجعة الإدارة',
                                ],
                            ];
                            continue;
                        }

                        // الخدمة مسجلة ومعتمدة بعقد الطبيب
                        $servicesCount += $quantity;
                        $doctorBasePrice = (float) ($specificComm->doctor_service_price ?? 0);
                        $itemBaseTotal = round($doctorBasePrice * $quantity, 2);
                        $isLaser = (bool) $specificComm->is_laser;

                        $itemComm = 0;
                        $calcDescription = '';

                        if ($specificComm->commission_type === CommissionTypeEnum::FIXED) {
                            $val = (float) $specificComm->commission_value;
                            $itemComm = $quantity * $val;
                            $calcDescription = "عمولة ثابتة ({$quantity} × {$val} ج) لخدمة {$service->name}";
                        } else {
                            $basePct = (float) $specificComm->commission_value;

                            // إذا كان الطبيب يحمل تارجت دائم، يطبق مباشرة نسبة ما بعد التارجت من أول الشهر
                            if ($isPermanentTargetAchieved && $contract && $contract->has_target) {
                                $elevatedPct = ($specificComm->target_commission_value !== null && (float) $specificComm->target_commission_value > 0)
                                    ? (float) $specificComm->target_commission_value
                                    : ($isLaser
                                        ? (float) ($contract->target_achieved_laser_percentage ?? $basePct)
                                        : (float) ($contract->target_achieved_other_percentage ?? $basePct));

                                if ($elevatedPct > 0) {
                                    $itemComm = $itemBaseTotal * ($elevatedPct / 100);
                                    $calcDescription = "نسبة تارجت دائم ({$elevatedPct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                } else {
                                    $itemComm = $itemBaseTotal * ($basePct / 100);
                                    $calcDescription = "نسبة أساسية ({$basePct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                }
                            } else {
                                // النسبة الأساسية قبل كسر التارجت
                                $itemComm = $itemBaseTotal * ($basePct / 100);
                                $calcDescription = "نسبة أساسية ({$basePct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                            }
                        }

                        $itemComm = round($itemComm, 2);
                        if ($itemComm > 0) {
                            $serviceCommissionsAmount += $itemComm;
                            $serviceCommissionItems[] = [
                                'type' => 'service_commission',
                                'description' => $calcDescription,
                                'amount' => $itemComm,
                                'is_addition' => true,
                                'reference_id' => $inv->id,
                                'reference_type' => 'invoice',
                                'metadata' => [
                                    'service_id' => $service->id,
                                    'service_name' => $service->name,
                                    'invoice_number' => $inv->invoice_number,
                                    'quantity' => $quantity,
                                    'doctor_service_price' => $doctorBasePrice,
                                    'commission_value' => (float) $specificComm->commission_value,
                                    'is_laser' => $isLaser,
                                ],
                            ];
                        }
                    }
                }
            }


            // 4. نظام التارجت والترقية للشريحة الأعلى (Marginal / Progressive Target)
            $targetAchieved = false;
            $targetBonusAmount = 0;

            if ($contract && $contract->has_target) {
                // أ. حالة الطبيب دائم التارجت (Permanent Target Achieved)
                if ($isPermanentTargetAchieved) {
                    $targetAchieved = true;

                    // تطبيق سعر الساعة بعد التارجت لكامل ساعات العمل
                    if ((float) $contract->target_achieved_hourly_rate > 0) {
                        $newHourlyRate = (float) $contract->target_achieved_hourly_rate;
                        $hourlyPay = round($totalWorkingHours * $newHourlyRate, 2);

                        // تحديث بند أجر الساعات في itemsToCreate
                        foreach ($itemsToCreate as &$createdItem) {
                            if ($createdItem['type'] === 'hourly_pay') {
                                $createdItem['amount'] = $hourlyPay;
                                $createdItem['description'] = "أجر ساعات العمل الفعلى بسعر التارجت الدائم ({$totalWorkingHours} ساعة × {$newHourlyRate} ج)";
                                break;
                            }
                        }
                        unset($createdItem);
                    }

                    // بونص التارجت
                    if ((float) $contract->target_bonus > 0) {
                        $targetBonusAmount = (float) $contract->target_bonus;
                        $itemsToCreate[] = [
                            'type' => 'target_bonus',
                            'description' => "مكافأة التارجت الدائم المعتمد للموظف",
                            'amount' => $targetBonusAmount,
                            'is_addition' => true,
                        ];
                    }
                }
                // ب. حالة الطبيب الخاضع للتقييم والتصعيد الشهري المتجدد
                elseif ((float) $contract->target_amount > 0) {
                    $targetThreshold = (float) $contract->target_amount;
                    $benchmarkType = $contract->target_type;

                    // التحقق المبدئي: هل تم كسر التارجت إجمالاً خلال هذا الشهر؟
                    $totalBenchmark = ($benchmarkType === TargetTypeEnum::CLINIC_REVENUE)
                        ? $clinicRevenueGenerated
                        : ($hourlyPay + $serviceCommissionsAmount);

                    if ($totalBenchmark >= $targetThreshold) {
                        $targetAchieved = true;
                        // ملاحظة جوهرية: لا نقوم بتعديل $user->achieved_target في جدول users إطلاقاً
                        // لضمان أن يبدأ الشهر القادم تلقائياً من الصفر بالنسب الأساسية دون أي تداخل

                        // 1. احتساب فرق الساعات
                        if ((float) $contract->target_achieved_hourly_rate > 0) {
                            $baseRate = (float) ($contract->hourly_rate ?? 0);
                            $newHourlyRate = (float) $contract->target_achieved_hourly_rate;

                            if ($benchmarkType === TargetTypeEnum::CLINIC_REVENUE) {
                                $ratioExceeded = ($clinicRevenueGenerated > 0)
                                    ? max(0, min(1, ($clinicRevenueGenerated - $targetThreshold) / $clinicRevenueGenerated))
                                    : 0;

                                $elevatedHours = $totalWorkingHours * $ratioExceeded;
                                $regularHours = $totalWorkingHours - $elevatedHours;

                                $hourlyPay = round(($regularHours * $baseRate) + ($elevatedHours * $newHourlyRate), 2);
                            } else {
                                $hourlyPay = round($totalWorkingHours * $baseRate, 2);
                            }

                            // تحديث بند أجر الساعات في itemsToCreate
                            foreach ($itemsToCreate as &$createdItem) {
                                if ($createdItem['type'] === 'hourly_pay') {
                                    $createdItem['amount'] = $hourlyPay;
                                    $createdItem['description'] = "أجر ساعات العمل الفعلى بعد كسر التارجت ({$totalWorkingHours} ساعة بمعدل تصاعدي)";
                                    break;
                                }
                            }
                            unset($createdItem);
                        }

                        // 2. إعادة احتساب عمولات الخدمات تصاعدياً (قبل وبعد كسر التارجت)
                        $serviceCommissionsAmount = 0;
                        $serviceCommissionItems = [];

                        // ترتيب الفواتير زمنياً لتتبع لحظة كسر التارجت بدقة
                        $sortedInvoices = $doctorInvoices->sortBy('created_at');
                        $accumulatedBenchmark = ($benchmarkType === TargetTypeEnum::CLINIC_REVENUE) ? 0 : $hourlyPay;

                        foreach ($sortedInvoices as $inv) {
                            foreach ($inv->items as $item) {
                                if (in_array($item->item_type, ['service', 'package_consumption']) && $item->service) {
                                    $service = $item->service;
                                    $quantity = (int) $item->quantity;
                                    $specificComm = $contract->serviceCommissions?->firstWhere('service_id', $service->id);

                                    // إذا كانت الخدمة غير مسجلة بعقد الطبيب تظل بعمولة 0 ج
                                    if (!$specificComm) {
                                        $serviceCommissionItems[] = [
                                            'type' => 'uncontracted_service',
                                            'description' => "خدمة خارج العقد: {$service->name} (كمية {$quantity}) - فاتورة {$inv->invoice_number} (تستوجب مراجعة الإدارة)",
                                            'amount' => 0,
                                            'is_addition' => true,
                                            'reference_id' => $inv->id,
                                            'reference_type' => 'invoice',
                                        ];
                                        continue;
                                    }

                                    $doctorBasePrice = (float) ($specificComm->doctor_service_price ?? 0);
                                    $itemBaseTotal = round($doctorBasePrice * $quantity, 2);
                                    $isLaser = (bool) $specificComm->is_laser;
                                    $basePct = (float) $specificComm->commission_value;

                                    // تحديد نسبة ما بعد التارجت للخدمة
                                    $elevatedPct = ($specificComm->target_commission_value !== null && (float) $specificComm->target_commission_value > 0)
                                        ? (float) $specificComm->target_commission_value
                                        : ($isLaser
                                            ? (float) ($contract->target_achieved_laser_percentage ?? $basePct)
                                            : (float) ($contract->target_achieved_other_percentage ?? $basePct));

                                    if ($elevatedPct <= 0) {
                                        $elevatedPct = $basePct;
                                    }

                                    $itemComm = 0;
                                    $calcDescription = '';

                                    if ($specificComm->commission_type === CommissionTypeEnum::FIXED) {
                                        $val = (float) $specificComm->commission_value;
                                        $itemComm = $quantity * $val;
                                        $calcDescription = "عمولة ثابتة ({$quantity} × {$val} ج) لخدمة {$service->name}";
                                    } else {
                                        // تتبع كسر التارجت
                                        if ($benchmarkType === TargetTypeEnum::CLINIC_REVENUE) {
                                            $prevBenchmark = $accumulatedBenchmark;
                                            $accumulatedBenchmark += (float) $item->total_price;

                                            if ($prevBenchmark >= $targetThreshold) {
                                                // البند تم بالكامل بعد كسر التارجت
                                                $itemComm = $itemBaseTotal * ($elevatedPct / 100);
                                                $calcDescription = "نسبة ترقية بعد التارجت ({$elevatedPct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                            } elseif ($accumulatedBenchmark > $targetThreshold) {
                                                // الفاتورة التي كُسر فيها التارجت (مجزأة)
                                                $itemPrice = max(0.01, (float) $item->total_price);
                                                $beforeRatio = min(1, max(0, ($targetThreshold - $prevBenchmark) / $itemPrice));
                                                $afterRatio = 1 - $beforeRatio;

                                                $commBefore = ($itemBaseTotal * $beforeRatio) * ($basePct / 100);
                                                $commAfter = ($itemBaseTotal * $afterRatio) * ($elevatedPct / 100);
                                                $itemComm = $commBefore + $commAfter;

                                                $calcDescription = "نسبة مجزأة (قبل {$basePct}% + بعد {$elevatedPct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                            } else {
                                                // قبل كسر التارجت
                                                $itemComm = $itemBaseTotal * ($basePct / 100);
                                                $calcDescription = "نسبة أساسية قبل التارجت ({$basePct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                            }
                                        } else {
                                            // DOCTOR_INCOME
                                            $prevBenchmark = $accumulatedBenchmark;
                                            $itemBaseComm = $itemBaseTotal * ($basePct / 100);
                                            $accumulatedBenchmark += $itemBaseComm;

                                            if ($prevBenchmark >= $targetThreshold) {
                                                $itemComm = $itemBaseTotal * ($elevatedPct / 100);
                                                $calcDescription = "نسبة ترقية بعد التارجت ({$elevatedPct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                            } elseif ($accumulatedBenchmark > $targetThreshold) {
                                                $denom = max(0.01, $itemBaseComm);
                                                $beforeRatio = min(1, max(0, ($targetThreshold - $prevBenchmark) / $denom));
                                                $afterRatio = 1 - $beforeRatio;

                                                $commBefore = ($itemBaseTotal * $beforeRatio) * ($basePct / 100);
                                                $commAfter = ($itemBaseTotal * $afterRatio) * ($elevatedPct / 100);
                                                $itemComm = $commBefore + $commAfter;

                                                $calcDescription = "نسبة مجزأة (قبل {$basePct}% + بعد {$elevatedPct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                            } else {
                                                $itemComm = $itemBaseComm;
                                                $calcDescription = "نسبة أساسية قبل التارجت ({$basePct}%) لخدمة {$service->name} على سعر الأساس ({$doctorBasePrice} ج)";
                                            }
                                        }
                                    }

                                    $itemComm = round($itemComm, 2);
                                    if ($itemComm > 0) {
                                        $serviceCommissionsAmount += $itemComm;
                                        $serviceCommissionItems[] = [
                                            'type' => 'service_commission',
                                            'description' => $calcDescription,
                                            'amount' => $itemComm,
                                            'is_addition' => true,
                                            'reference_id' => $inv->id,
                                            'reference_type' => 'invoice',
                                            'metadata' => [
                                                'service_id' => $service->id,
                                                'service_name' => $service->name,
                                                'invoice_number' => $inv->invoice_number,
                                                'quantity' => $quantity,
                                                'doctor_service_price' => $doctorBasePrice,
                                                'is_laser' => $isLaser,
                                            ],
                                        ];
                                    }
                                }
                            }
                        }

                        // 3. مكافأة كسر التارجت (Bonus)
                        if ((float) $contract->target_bonus > 0) {
                            $targetBonusAmount = (float) $contract->target_bonus;
                            $itemsToCreate[] = [
                                'type' => 'target_bonus',
                                'description' => "مكافأة كسر التارجت المحقق لهذا الشهر ({$totalBenchmark} ج من أصل {$targetThreshold} ج)",
                                'amount' => $targetBonusAmount,
                                'is_addition' => true,
                            ];
                        }
                    }
                }
            }

            // دمج بنود عمولات الخدمات
            $itemsToCreate = array_merge($itemsToCreate, $serviceCommissionItems);

            // 5. عمولات التمريض (جلسات الأجهزة + مبيعات الأدوية والمستلزمات)
            // $deviceSessionsCount = 0;
            // $deviceCommissionsAmount = 0;
            // $productSalesTotal = 0;
            // $productCommissionsAmount = 0;

            // if ($contract && ($contract->contract_type === ContractTypeEnum::NURSE || (float) $contract->device_session_commission > 0 || (float) $contract->medication_sales_percentage > 0)) {
            //     $nurseInvoices = Invoice::with(['items.service'])
            //         ->where('nurse_id', $userId)
            //         ->whereBetween('created_at', [$startDate, $endDate])
            //         ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
            //         ->get();

            //     $deviceRate = (float) $contract->device_session_commission;
            //     $medicationPct = (float) $contract->medication_sales_percentage;

            //     foreach ($nurseInvoices as $inv) {
            //         foreach ($inv->items as $item) {
            //             if ($item->item_type === 'service' && $item->service && $item->service->type === ServiceTypeEnum::DEVICE) {
            //                 $deviceSessionsCount += (int) $item->quantity;
            //             } elseif ($item->item_type === 'product') {
            //                 $productSalesTotal += (float) $item->total_price;
            //             }
            //         }
            //     }

            //     if ($deviceSessionsCount > 0 && $deviceRate > 0) {
            //         $deviceCommissionsAmount = round($deviceSessionsCount * $deviceRate, 2);
            //         $itemsToCreate[] = [
            //             'type' => 'device_commission',
            //             'description' => "عمولة جلسات أجهزة تمريض ({$deviceSessionsCount} جلسة × {$deviceRate} ج)",
            //             'amount' => $deviceCommissionsAmount,
            //             'is_addition' => true,
            //         ];
            //     }

            //     if ($productSalesTotal > 0 && $medicationPct > 0) {
            //         $productCommissionsAmount = round($productSalesTotal * ($medicationPct / 100), 2);
            //         $itemsToCreate[] = [
            //             'type' => 'product_commission',
            //             'description' => "عمولة مبيعات أدوية ومستلزمات ({$medicationPct}% من مبيعات {$productSalesTotal} ج)",
            //             'amount' => $productCommissionsAmount,
            //             'is_addition' => true,
            //         ];
            //     }
            // }

            // 5. عمولات التمريض (جلسات الأجهزة + مبيعات الأدوية والمستلزمات)
            $deviceSessionsCount = 0;
            $deviceCommissionsAmount = 0;
            $productSalesTotal = 0;
            $productCommissionsAmount = 0;

            $rawCommType = $contract?->medication_commission_type;
            $commType = $rawCommType instanceof \BackedEnum ? $rawCommType->value : (string) ($rawCommType ?? CommissionTypeEnum::PERCENTAGE->value);
            $isMedPercentage = $commType === CommissionTypeEnum::PERCENTAGE->value;

            $commValue = $isMedPercentage
                ? (float) ((float) $contract?->medication_commission_value > 0 ? $contract?->medication_commission_value : ($contract?->medication_sales_percentage ?? 0))
                : (float) ($contract?->medication_commission_value ?? 0);

            $hasMedicationComm = $commValue > 0;

            if ($contract && ($contract->contract_type === ContractTypeEnum::NURSE || (float) $contract->device_session_commission > 0 || $hasMedicationComm)) {

                // --- أولاً: جلسات الأجهزة (تعتمد حصراً على الفواتير المحددة لهذا الممرض) ---
                $deviceRate = (float) $contract->device_session_commission;

                if ($deviceRate > 0) {
                    $nurseDeviceInvoices = Invoice::with(['items.service', 'doctor.activeContract.serviceCommissions'])
                        ->where('nurse_id', $userId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
                        ->get();

                    foreach ($nurseDeviceInvoices as $inv) {
                        foreach ($inv->items as $item) {
                            if (in_array($item->item_type, ['service', 'package_consumption'])) {
                                $service = $item->service;
                                if (!$service && $item->service_id) {
                                    $service = Service::find($item->service_id);
                                }

                                if (!$service) {
                                    continue;
                                }

                                // تحديد هل الخدمة جلسة أجهزة أو ليزر:
                                // 1. نوع الخدمة الأساسي في جدول الخدمات هو جهاز (device)
                                $isDeviceOrLaser = ($service->type === ServiceTypeEnum::DEVICE);

                                // 2. أو معلّمة كجلسة ليزر في عقد الطبيب المنفّذ للجلسة
                                if (!$isDeviceOrLaser && $inv->doctor && $inv->doctor->activeContract) {
                                    $docCommission = $inv->doctor->activeContract->serviceCommissions
                                        ->firstWhere('service_id', $service->id);
                                    if ($docCommission && (bool) $docCommission->is_laser) {
                                        $isDeviceOrLaser = true;
                                    }
                                }

                                // 3. أو معلّمة كجلسة ليزر في بنود عمولات أي عقد معتمد في النظام
                                if (!$isDeviceOrLaser) {
                                    $isDeviceOrLaser = ContractServiceCommission::where('service_id', $service->id)
                                        ->where('is_laser', true)
                                        ->exists();
                                }

                                if ($isDeviceOrLaser) {
                                    $deviceSessionsCount += (int) $item->quantity;
                                }
                            }
                        }
                    }

                    if ($deviceSessionsCount > 0) {
                        $deviceCommissionsAmount = round($deviceSessionsCount * $deviceRate, 2);
                        $itemsToCreate[] = [
                            'type' => 'device_commission',
                            'description' => "عمولة جلسات أجهزة تمريض ({$deviceSessionsCount} جلسة × {$deviceRate} ج)",
                            'amount' => $deviceCommissionsAmount,
                            'is_addition' => true,
                        ];
                    }
                }

                // --- ثانياً: عمولة المنتجات والأدوية (تُحسب تلقائياً من إجمالي مبيعات المركز بدون شرط nurse_id) ---
                if ($commValue > 0) {
                    // جلب جميع مبيعات المنتجات في المركز خلال الفترة
                    $productInvoices = Invoice::with('items')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
                        ->get();

                    $productSalesTotal = 0;
                    $productItemsCount = 0;

                    foreach ($productInvoices as $inv) {
                        foreach ($inv->items as $item) {
                            if ($item->item_type === 'product') {
                                $productSalesTotal += (float) $item->total_price;
                                $productItemsCount += (int) $item->quantity;
                            }
                        }
                    }

                    if ($commType === 'percentage' && $productSalesTotal > 0) {
                        // خيار النسبة المئوية: نسبة من إجمالي المبيعات
                        $productCommissionsAmount = round($productSalesTotal * ($commValue / 100), 2);
                        $itemsToCreate[] = [
                            'type' => 'product_commission',
                            'description' => "عمولة مبيعات أدوية ومستلزمات ({$commValue}% من مبيعات {$productSalesTotal} ج)",
                            'amount' => $productCommissionsAmount,
                            'is_addition' => true,
                        ];
                    } elseif ($commType === 'fixed' && $productItemsCount > 0) {
                        // خيار القيمة الثابتة: مبلغ ثابت لكل قطعة/منتج مباع
                        $productCommissionsAmount = round($productItemsCount * $commValue, 2);
                        $itemsToCreate[] = [
                            'type' => 'product_commission',
                            'description' => "عمولة مبيعات أدوية ومستلزمات ({$productItemsCount} صنف × {$commValue} ج)",
                            'amount' => $productCommissionsAmount,
                            'is_addition' => true,
                        ];
                    }
                }
            }

            // 6. عمولات الاستقبال والإدارة من نسب الأقسام
            $departmentCommissionsAmount = 0;
            if ($contract && $contract->applies_department_commission && !empty($contract->department_commissions)) {
                foreach ($contract->department_commissions as $deptRule) {
                    $deptId = $deptRule['department_id'] ?? null;
                    $deptPct = (float) ($deptRule['percentage'] ?? 0);

                    if ($deptId && $deptPct > 0) {
                        $dept = Department::find($deptId);
                        $deptName = $dept?->name ?? "قسم رقم {$deptId}";

                        $deptRevenue = (float) InvoiceItem::where(function ($query) use ($deptId) {
                                $query->whereHas('service', fn($q) => $q->where('department_id', $deptId))
                                      ->orWhereHas('package', fn($q) => $q->where('department_id', $deptId));
                            })
                            ->whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate])->where('status', '!=', InvoiceStatusEnum::CANCELLED->value))
                            ->sum('total_price');

                        if ($deptRevenue > 0) {
                            $deptComm = round($deptRevenue * ($deptPct / 100), 2);
                            $departmentCommissionsAmount += $deptComm;

                            $itemsToCreate[] = [
                                'type' => 'department_commission',
                                'description' => "عمولة استقبال من إجمالي مبيعات {$deptName} ({$deptPct}% من {$deptRevenue} ج)",
                                'amount' => $deptComm,
                                'is_addition' => true,
                                'metadata' => [
                                    'department_id' => $deptId,
                                    'department_name' => $deptName,
                                    'department_revenue' => $deptRevenue,
                                    'percentage' => $deptPct,
                                ],
                            ];
                        }
                    }
                }
            }

            // 7. بدلات واستقطاعات يدوية وملاحظات
            $otherAllowances = (float) ($extra['other_allowances'] ?? 0);
            $deductions = (float) ($extra['deductions'] ?? 0);
            $notes = $extra['notes'] ?? null;

            if ($otherAllowances > 0) {
                $itemsToCreate[] = [
                    'type' => 'allowance',
                    'description' => 'بدلات ومكافآت إضافية يدوية',
                    'amount' => $otherAllowances,
                    'is_addition' => true,
                ];
            }

            if ($deductions > 0) {
                $itemsToCreate[] = [
                    'type' => 'deduction',
                    'description' => 'استقطاعات وخصومات يدوية',
                    'amount' => $deductions,
                    'is_addition' => false,
                ];
            }

            // 8. احتساب الإجمالي والصافي
            $grossSalary = round(
                $basicSalary
                + $hourlyPay
                + $overtimeAmount
                + $holidayAllowanceAmount
                + $serviceCommissionsAmount
                + $deviceCommissionsAmount
                + $productCommissionsAmount
                + $departmentCommissionsAmount
                + $targetBonusAmount
                + $otherAllowances,
                2
            );

            $netSalary = round($grossSalary - $lateDeductionAmount - $deductions, 2);
            if ($netSalary < 0) {
                $netSalary = 0;
            }

            // 9. حفظ سجل الراتب وبنوده
            $payrollData = [
                'user_id' => $userId,
                'contract_id' => $contract?->id,
                'month' => $month,
                'start_date' => $formattedStart,
                'end_date' => $formattedEnd,
                'basic_salary' => $basicSalary,
                'shifts_count' => $shiftsCount,
                'total_working_hours' => round($totalWorkingHours, 2),
                'hourly_pay' => $hourlyPay,
                'overtime_hours' => round($overtimeHours, 2),
                'overtime_amount' => $overtimeAmount,
                'late_minutes' => $lateMinutes,
                'late_deduction_amount' => $lateDeductionAmount,
                'holiday_days' => $holidayDays,
                'holiday_allowance_amount' => $holidayAllowanceAmount,
                'services_count' => $servicesCount,
                'service_commissions_amount' => $serviceCommissionsAmount,
                'device_sessions_count' => $deviceSessionsCount,
                'device_commissions_amount' => $deviceCommissionsAmount,
                'product_sales_total' => $productSalesTotal,
                'product_commissions_amount' => $productCommissionsAmount,
                'department_commissions_amount' => $departmentCommissionsAmount,
                'target_achieved' => $targetAchieved,
                'target_bonus_amount' => $targetBonusAmount,
                'other_allowances' => $otherAllowances,
                'deductions' => $deductions,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary,
                'status' => PayrollStatusEnum::DRAFT->value,
                'notes' => $notes,
            ];

            // البحث عن مسير موجود لنفس الفترة (مع دعم السجلات المحذوفة withTrashed لتفادي أي خطأ Duplicate entry)
            $existing = Payroll::withTrashed()
                ->where('user_id', $userId)
                ->where(function ($query) use ($formattedStart, $formattedEnd, $month) {
                    $query->where(function ($q) use ($formattedStart, $formattedEnd) {
                        $q->where('start_date', $formattedStart)
                          ->where('end_date', $formattedEnd);
                    })->orWhere(function ($q) use ($month) {
                        $q->where('month', $month)
                          ->whereNull('start_date');
                    });
                })
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($payrollData);
                $payroll = $existing;
            } else {
                $payroll = Payroll::create($payrollData);
            }

            // حذف البنود السابقة وإعادة تسجيل البنود التفصيلية
            $payroll->items()->delete();
            foreach ($itemsToCreate as $item) {
                $payroll->items()->create($item);
            }

            return $payroll;
        });
    }

    public function update($id, UpdatePayrollRequest $request)
    {
        $payroll = Payroll::findOrFail($id);
        if ($payroll->status === PayrollStatusEnum::PAID) {
            return API::newInstance()->isError('لا يمكن تعديل مسير راتب تم صرفه')->build();
        }

        $validated = $request->validated();
        $otherAllowances = isset($validated['other_allowances']) ? (float) $validated['other_allowances'] : (float) $payroll->other_allowances;
        $deductions = isset($validated['deductions']) ? (float) $validated['deductions'] : (float) $payroll->deductions;

        $totalWorkingHours = isset($validated['total_working_hours']) ? (float) $validated['total_working_hours'] : (float) $payroll->total_working_hours;
        $contract = $payroll->contract;
        $hourlyRate = (float) ($contract?->hourly_rate ?? ($payroll->total_working_hours > 0 ? ($payroll->hourly_pay / $payroll->total_working_hours) : 0));
        $hourlyPay = isset($validated['total_working_hours']) ? round($totalWorkingHours * $hourlyRate, 2) : (float) $payroll->hourly_pay;

        if (isset($validated['total_working_hours'])) {
            $hourlyItem = $payroll->items()->where('type', 'hourly_pay')->first();
            if ($hourlyPay > 0) {
                if ($hourlyItem) {
                    $hourlyItem->update([
                        'amount' => $hourlyPay,
                        'description' => "أجر ساعات العمل الفعلى ({$totalWorkingHours} ساعة × {$hourlyRate} ج)",
                    ]);
                } else {
                    $payroll->items()->create([
                        'type' => 'hourly_pay',
                        'description' => "أجر ساعات العمل الفعلى ({$totalWorkingHours} ساعة × {$hourlyRate} ج)",
                        'amount' => $hourlyPay,
                        'is_addition' => true,
                    ]);
                }
            } elseif ($hourlyItem) {
                $hourlyItem->delete();
            }
        }

        // إعادة حساب الإجمالي والصافي بناءً على التعديل اليدوي
        $grossSalary = round(
            $payroll->basic_salary
            + $hourlyPay
            + $payroll->overtime_amount
            + $payroll->holiday_allowance_amount
            + $payroll->service_commissions_amount
            + $payroll->device_commissions_amount
            + $payroll->product_commissions_amount
            + $payroll->department_commissions_amount
            + $payroll->target_bonus_amount
            + $otherAllowances,
            2
        );

        $netSalary = max(0, round($grossSalary - $payroll->late_deduction_amount - $deductions, 2));

        $payroll->update([
            'total_working_hours' => $totalWorkingHours,
            'hourly_pay' => $hourlyPay,
            'other_allowances' => $otherAllowances,
            'deductions' => $deductions,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'notes' => $validated['notes'] ?? $payroll->notes,
        ]);

        return API::newInstance()
            ->isOk('تم تحديث مسير الراتب بنجاح')
            ->setData(new PayrollResource($payroll->load(['user', 'contract', 'items'])))
            ->build();
    }

    public function approve($id)
    {
        $payroll = Payroll::findOrFail($id);
        if ($payroll->status === PayrollStatusEnum::PAID) {
            return API::newInstance()->isError('المرتب منصرف بالفعل')->build();
        }

        $payroll->update([
            'status' => PayrollStatusEnum::APPROVED->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return API::newInstance()
            ->isOk('تم اعتماد مسير الراتب بنجاح')
            ->setData(new PayrollResource($payroll->load(['user', 'contract', 'approver'])))
            ->build();
    }

    public function pay($id, PayPayrollRequest $request)
    {
        $payroll = Payroll::findOrFail($id);
        if ($payroll->status === PayrollStatusEnum::PAID) {
            return API::newInstance()->isError('تم صرف هذا الراتب من قبل')->build();
        }

        $validated = $request->validated();

        $payroll->update([
            'status' => PayrollStatusEnum::PAID->value,
            'paid_by' => Auth::id(),
            'paid_at' => now(),
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'] ?? $payroll->notes,
        ]);

        return API::newInstance()
            ->isOk('تم تأكيد صرف الراتب بنجاح')
            ->setData(new PayrollResource($payroll->load(['user', 'contract', 'payer'])))
            ->build();
    }

    public function destroy($id)
    {
        $payroll = Payroll::findOrFail($id);
        if ($payroll->status === PayrollStatusEnum::PAID) {
            return API::newInstance()->isError('لا يمكن حذف مسير راتب تم صرفه بالفعل')->build();
        }

        $payroll->delete();

        return API::newInstance()->isOk('تم حذف مسير الراتب بنجاح')->build();
    }
}
