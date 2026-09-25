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

            $hourlyRate = (float) ($contract?->hourly_rate ?? 0);
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

            $doctorInvoices = Invoice::with(['items.service'])
                ->where('doctor_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
                ->get();

            $serviceCommissionItems = [];

            foreach ($doctorInvoices as $inv) {
                $clinicRevenueGenerated += (float) $inv->grand_total;

                foreach ($inv->items as $item) {
                    if ($item->item_type === 'service' && $item->service) {
                        $servicesCount += (int) $item->quantity;
                        $service = $item->service;

                        // البحث عن عمولة مخصصة لهذه الخدمة في العقد
                        $specificComm = $contract?->serviceCommissions?->firstWhere('service_id', $service->id);

                        $itemComm = 0;
                        $calcDescription = '';

                        if ($specificComm) {
                            if ($specificComm->commission_type === CommissionTypeEnum::FIXED) {
                                $val = (float) $specificComm->commission_value;
                                $itemComm = $item->quantity * $val;
                                $calcDescription = "عمولة ثابتة ({$item->quantity} × {$val} ج) لخدمة {$service->name}";
                            } else {
                                $pct = (float) $specificComm->commission_value;
                                $itemComm = (float) $item->total_price * ($pct / 100);
                                $calcDescription = "نسبة مئوية ({$pct}%) من خدمة {$service->name} (إجمالي {$item->total_price} ج)";
                            }
                        } else {
                            // التحقق إذا كانت الخدمة ليزر
                            $isLaser = str_contains(mb_strtolower($service->name), 'ليزر') || str_contains(strtolower($service->name), 'laser');
                            
                            if ($isLaser && (float) ($contract?->laser_service_commission_percentage ?? 0) > 0) {
                                $pct = (float) $contract->laser_service_commission_percentage;
                                $itemComm = (float) $item->total_price * ($pct / 100);
                                $calcDescription = "نسبة ليزر ({$pct}%) من خدمة {$service->name} (فاتورة {$inv->invoice_number})";
                            } elseif ((float) ($contract?->other_service_commission_percentage ?? 0) > 0) {
                                $pct = (float) $contract->other_service_commission_percentage;
                                $itemComm = (float) $item->total_price * ($pct / 100);
                                $calcDescription = "نسبة خدمات أخرى ({$pct}%) لخدمة {$service->name}";
                            } elseif ((float) ($contract?->default_service_commission_value ?? 0) > 0) {
                                if ($contract->default_service_commission_type === CommissionTypeEnum::FIXED) {
                                    $val = (float) $contract->default_service_commission_value;
                                    $itemComm = $item->quantity * $val;
                                    $calcDescription = "عمولة افتراضية ثابتة ({$item->quantity} × {$val} ج) لخدمة {$service->name}";
                                } else {
                                    $pct = (float) $contract->default_service_commission_value;
                                    $itemComm = (float) $item->total_price * ($pct / 100);
                                    $calcDescription = "نسبة افتراضية عامة ({$pct}%) لخدمة {$service->name}";
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
                                    'quantity' => $item->quantity,
                                    'total_price' => (float) $item->total_price,
                                ],
                            ];
                        }
                    }
                }
            }

            // 4. نظام التارجت والترقية للشريحة الأعلى (Generic Tiered Target)
            $targetAchieved = false;
            $targetBonusAmount = 0;

            if ($contract && $contract->has_target && (float) $contract->target_amount > 0) {
                $targetThreshold = (float) $contract->target_amount;
                $benchmarkValue = ($contract->target_type === TargetTypeEnum::CLINIC_REVENUE)
                    ? $clinicRevenueGenerated
                    : ($hourlyPay + $serviceCommissionsAmount);

                if ($benchmarkValue >= $targetThreshold) {
                    $targetAchieved = true;
                    $user->achieved_target = true;
                    $user->save();

                    // تطبيق أسعار الشريحة الأعلى إذا تم تحقيق التارجت
                    if ((float) $contract->target_achieved_hourly_rate > 0) {
                        $newHourlyRate = (float) $contract->target_achieved_hourly_rate;
                        $hourlyPay = round($totalWorkingHours * $newHourlyRate, 2);
                    }

                    // إعادة احتساب نسب الليزر والخدمات للشريحة الأعلى
                    $elevatedLaserPct = (float) $contract->target_achieved_laser_percentage;
                    $elevatedOtherPct = (float) $contract->target_achieved_other_percentage;

                    if ($elevatedLaserPct > 0 || $elevatedOtherPct > 0) {
                        $serviceCommissionsAmount = 0;
                        $serviceCommissionItems = [];

                        foreach ($doctorInvoices as $inv) {
                            foreach ($inv->items as $item) {
                                if ($item->item_type === 'service' && $item->service) {
                                    $service = $item->service;
                                    $specificComm = $contract->serviceCommissions?->firstWhere('service_id', $service->id);

                                    $itemComm = 0;
                                    $calcDescription = '';

                                    if ($specificComm) {
                                        // العمولات المحددة بالاسم تظل كما هي
                                        if ($specificComm->commission_type === CommissionTypeEnum::FIXED) {
                                            $val = (float) $specificComm->commission_value;
                                            $itemComm = $item->quantity * $val;
                                            $calcDescription = "عمولة ثابتة ({$item->quantity} × {$val} ج) لخدمة {$service->name}";
                                        } else {
                                            $pct = (float) $specificComm->commission_value;
                                            $itemComm = (float) $item->total_price * ($pct / 100);
                                            $calcDescription = "نسبة مئوية ({$pct}%) من خدمة {$service->name}";
                                        }
                                    } else {
                                        $isLaser = str_contains(mb_strtolower($service->name), 'ليزر') || str_contains(strtolower($service->name), 'laser');
                                        if ($isLaser && $elevatedLaserPct > 0) {
                                            $itemComm = (float) $item->total_price * ($elevatedLaserPct / 100);
                                            $calcDescription = "نسبة ليزر بعد التارجت ({$elevatedLaserPct}%) لخدمة {$service->name}";
                                        } elseif ($elevatedOtherPct > 0) {
                                            $itemComm = (float) $item->total_price * ($elevatedOtherPct / 100);
                                            $calcDescription = "نسبة خدمات بعد التارجت ({$elevatedOtherPct}%) لخدمة {$service->name}";
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
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    if ((float) $contract->target_bonus > 0) {
                        $targetBonusAmount = (float) $contract->target_bonus;
                        $itemsToCreate[] = [
                            'type' => 'target_bonus',
                            'description' => "مكافأة كسر التارجت المحقق ({$benchmarkValue} ج من أصل {$targetThreshold} ج)",
                            'amount' => $targetBonusAmount,
                            'is_addition' => true,
                        ];
                    }
                } else {
                    $user->achieved_target = false;
                    $user->save();
                }
            }

            // دمج بنود عمولات الخدمات
            $itemsToCreate = array_merge($itemsToCreate, $serviceCommissionItems);

            // 5. عمولات التمريض (جلسات الأجهزة + مبيعات الأدوية والمستلزمات)
            $deviceSessionsCount = 0;
            $deviceCommissionsAmount = 0;
            $productSalesTotal = 0;
            $productCommissionsAmount = 0;

            if ($contract && ($contract->contract_type === ContractTypeEnum::NURSE || (float) $contract->device_session_commission > 0 || (float) $contract->medication_sales_percentage > 0)) {
                $nurseInvoices = Invoice::with(['items.service'])
                    ->where('nurse_id', $userId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
                    ->get();

                $deviceRate = (float) $contract->device_session_commission;
                $medicationPct = (float) $contract->medication_sales_percentage;

                foreach ($nurseInvoices as $inv) {
                    foreach ($inv->items as $item) {
                        if ($item->item_type === 'service' && $item->service && $item->service->type === ServiceTypeEnum::DEVICE) {
                            $deviceSessionsCount += (int) $item->quantity;
                        } elseif ($item->item_type === 'product') {
                            $productSalesTotal += (float) $item->total_price;
                        }
                    }
                }

                if ($deviceSessionsCount > 0 && $deviceRate > 0) {
                    $deviceCommissionsAmount = round($deviceSessionsCount * $deviceRate, 2);
                    $itemsToCreate[] = [
                        'type' => 'device_commission',
                        'description' => "عمولة جلسات أجهزة تمريض ({$deviceSessionsCount} جلسة × {$deviceRate} ج)",
                        'amount' => $deviceCommissionsAmount,
                        'is_addition' => true,
                    ];
                }

                if ($productSalesTotal > 0 && $medicationPct > 0) {
                    $productCommissionsAmount = round($productSalesTotal * ($medicationPct / 100), 2);
                    $itemsToCreate[] = [
                        'type' => 'product_commission',
                        'description' => "عمولة مبيعات أدوية ومستلزمات ({$medicationPct}% من مبيعات {$productSalesTotal} ج)",
                        'amount' => $productCommissionsAmount,
                        'is_addition' => true,
                    ];
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

                        $deptRevenue = (float) InvoiceItem::whereHas('service', fn($q) => $q->where('department_id', $deptId))
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

        // إعادة حساب الإجمالي والصافي بناءً على التعديل اليدوي
        $grossSalary = round(
            $payroll->basic_salary
            + $payroll->hourly_pay
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
