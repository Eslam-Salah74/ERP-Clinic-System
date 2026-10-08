<?php

namespace Modules\Reception\Services\Report;

use App\Enums\UserType;
use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Models\Appointment;
use Modules\Reception\Models\FollowUp;
use Modules\Reception\Models\Invoice;
use Modules\Setup\Enums\ServiceTypeEnum;

class DoctorReportService
{
    /**
     * تقرير أداء وإيرادات الأطباء المفصل (كشوفات، خدمات، جلسات، دخل المركز، عمولات، وصافي المركز)
     */
    public function getDoctorsReport($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $doctorId  = $request->input('doctor_id');
        $deptId    = $request->input('department_id');

        // جلب الأطباء المستهدفين
        $doctorsQuery = User::query()
            ->with(['department', 'activeContract.serviceCommissions'])
            ->where(function ($q) {
                $q->where('type', UserType::DOCTOR)
                  ->orWhereHas('roles', fn($r) => $r->where('name', 'Doctor'));
            });

        if ($doctorId) {
            $doctorsQuery->where('id', $doctorId);
        }

        if ($deptId) {
            $doctorsQuery->where('department_id', $deptId);
        }

        $doctors = $doctorsQuery->get();

        if ($doctors->isEmpty()) {
            return API::newInstance()->isError('لا يوجد أطباء مطابقين لمحددات البحث.')->build();
        }

        $doctorIds = $doctors->pluck('id')->toArray();

        // 1. جلب فواتير الأطباء خلال الفترة
        $invoices = Invoice::with(['items.service.department', 'patient'])
            ->whereIn('doctor_id', $doctorIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
            ->get();

        // 2. جلب المتابعات الخاصة بهؤلاء الأطباء
        $followUps = FollowUp::whereIn('doctor_id', $doctorIds)
            ->whereBetween('follow_up_date', [$startDate, $endDate])
            ->get();

        $doctorsReport = [];
        $grandTotalRevenueAllDoctors = 0.00;
        $grandTotalCommissionsAll    = 0.00;
        $grandTotalClinicNetAll      = 0.00;

        foreach ($doctors as $doc) {
            $docInvoices = $invoices->where('doctor_id', $doc->id);
            $docFollowUps = $followUps->where('doctor_id', $doc->id);

            $consultationsCount = 0;
            $consultationsRev   = 0.00;
            $servicesCount      = 0;
            $servicesRev        = 0.00;
            $devicesCount       = 0;
            $devicesRev         = 0.00;
            $totalDocRevenue    = 0.00;
            $totalDocCommission = 0.00;

            $servicesBreakdown = [];

            foreach ($docInvoices as $inv) {
                foreach ($inv->items as $item) {
                    if ($item->item_type !== 'service' || !$item->service) {
                        continue;
                    }

                    $service   = $item->service;
                    $itemTotal = (float) $item->total_price;
                    $itemQty   = (int) $item->quantity;

                    $totalDocRevenue += $itemTotal;

                    // تصنيف البند
                    if ($service->type === ServiceTypeEnum::CONSULTATION) {
                        $consultationsCount += $itemQty;
                        $consultationsRev   += $itemTotal;
                    } elseif ($service->type === ServiceTypeEnum::DEVICE) {
                        $devicesCount += $itemQty;
                        $devicesRev   += $itemTotal;
                    } else {
                        $servicesCount += $itemQty;
                        $servicesRev   += $itemTotal;
                    }

                    // حساب عمولة الطبيب
                    $itemComm = $this->calculateItemCommission($item, $doc->activeContract, (bool) ($doc->achieved_target ?? false));
                    $totalDocCommission += $itemComm;

                    // تجميع الخدمات المقدمة من هذا الطبيب
                    $sId = $service->id;
                    if (!isset($servicesBreakdown[$sId])) {
                        $servicesBreakdown[$sId] = [
                            'service_id'   => $sId,
                            'service_name' => $service->name,
                            'type'         => $service->type?->value ?? 'service',
                            'type_arabic'  => match ($service->type) {
                                ServiceTypeEnum::CONSULTATION => 'كشف طبي',
                                ServiceTypeEnum::DEVICE       => 'جلسة جهاز',
                                default                       => 'خدمة / جلسة',
                            },
                            'count'        => 0,
                            'total_revenue'=> 0.00,
                            'unit_price'   => (float) $item->unit_price,
                        ];
                    }
                    $servicesBreakdown[$sId]['count']         += $itemQty;
                    $servicesBreakdown[$sId]['total_revenue'] += $itemTotal;
                }
            }

            $totalDocRevenue    = round($totalDocRevenue, 2);
            $totalDocCommission = round($totalDocCommission, 2);
            $clinicNetShare     = round(max(0, $totalDocRevenue - $totalDocCommission), 2);

            $grandTotalRevenueAllDoctors += $totalDocRevenue;
            $grandTotalCommissionsAll    += $totalDocCommission;
            $grandTotalClinicNetAll      += $clinicNetShare;

            $servicesSorted = collect($servicesBreakdown)
                ->sortByDesc('count')
                ->values()
                ->all();

            $doctorsReport[] = [
                'doctor_id'              => $doc->id,
                'doctor_name'            => $doc->name,
                'doctor_phone'           => $doc->phone,
                'department_id'          => $doc->department_id,
                'department_name'        => $doc->department?->name ?? 'غير محدد',
                'contract_type'          => $doc->activeContract?->contract_type?->value ?? 'بدون عقد مسجل',
                'metrics'                => [
                    'consultations_count'    => $consultationsCount,
                    'consultations_revenue'  => round($consultationsRev, 2),
                    'follow_ups_count'       => $docFollowUps->count(),
                    'services_count'         => $servicesCount,
                    'services_revenue'       => round($servicesRev, 2),
                    'devices_count'          => $devicesCount,
                    'devices_revenue'        => round($devicesRev, 2),
                    'total_patients_treated' => $docInvoices->pluck('patient_id')->filter()->unique()->count(),
                    'total_invoices_count'   => $docInvoices->count(),
                ],
                'financial_performance'  => [
                    'total_revenue_generated'=> $totalDocRevenue, // دخل المركز كام
                    'doctor_commission_dues' => $totalDocCommission, // عمولة ومستحقات الطبيب
                    'clinic_net_share'       => $clinicNetShare, // صافي دخل المركز من الطبيب
                    'clinic_margin_percent'  => $totalDocRevenue > 0 ? round(($clinicNetShare / $totalDocRevenue) * 100, 2) : 0.00,
                ],
                'services_rendered'      => $servicesSorted,
            ];
        }

        // ترتيب الأطباء تنازلياً حسب إجمالي الدخل الذي أدخلوه للمركز
        usort($doctorsReport, fn($a, $b) => $b['financial_performance']['total_revenue_generated'] <=> $a['financial_performance']['total_revenue_generated']);

        // إضافة الترتيب العام
        foreach ($doctorsReport as $idx => &$docData) {
            $docData['overall_rank'] = $idx + 1;
        }
        unset($docData);

        return API::newInstance()
            ->isOk('تم توليد تقرير أداء وإيرادات الأطباء بنجاح')
            ->setData([
                'report_title'       => 'تقرير أداء وإيرادات الأطباء المفصل',
                'period'             => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $startDate->diffInDays($endDate) + 1,
                ],
                'doctors_count'      => count($doctorsReport),
                'aggregate_summary'  => [
                    'total_revenue_all_doctors'     => round($grandTotalRevenueAllDoctors, 2),
                    'total_commissions_all_doctors' => round($grandTotalCommissionsAll, 2),
                    'total_clinic_net_share'        => round($grandTotalClinicNetAll, 2),
                ],
                'doctors'            => $doctorsReport,
            ])
            ->build();
    }

    /**
     * احتساب عمولة الطبيب على بند الخدمة بناءً على العقد
     */
    private function calculateItemCommission($item, $contract, bool $isPermanentTarget = false): float
    {
        if (!$contract) {
            return 0.00;
        }

        $service = $item->service;
        if (!$service) {
            return 0.00;
        }

        $quantity = (float) $item->quantity;
        $itemTotal = (float) $item->total_price;

        // 1. فحص وجود عمولة مخصصة في جدول contract_service_commissions
        $specificComm = $contract->serviceCommissions?->firstWhere('service_id', $service->id);

        if ($specificComm) {
            $customPrice = (float) ($specificComm->doctor_service_price ?? 0);
            $baseTotal = ($customPrice > 0) ? round($customPrice * $quantity, 2) : $itemTotal;

            if ($specificComm->commission_type === CommissionTypeEnum::FIXED) {
                return round((float) $specificComm->commission_value * $quantity, 2);
            }

            $basePct = (float) $specificComm->commission_value;
            $isLaser = (bool) $specificComm->is_laser;

            if ($isPermanentTarget && $contract->has_target) {
                $elevatedPct = ($specificComm->target_commission_value !== null && (float) $specificComm->target_commission_value > 0)
                    ? (float) $specificComm->target_commission_value
                    : ($isLaser
                        ? (float) ($contract->target_achieved_laser_percentage ?? $basePct)
                        : (float) ($contract->target_achieved_other_percentage ?? $basePct));

                $appliedPct = ($elevatedPct > 0) ? $elevatedPct : $basePct;
                return round($baseTotal * ($appliedPct / 100), 2);
            }

            return round($baseTotal * ($basePct / 100), 2);
        }

        // 2. خدمة غير مدرجة بالقائمة المخصصة: تطبيق نسبة الخدمات الأخرى على إجمالي الفاتورة
        $otherPct = (float) ($contract->other_service_commission_percentage ?? 0);
        if ($otherPct <= 0 && (float) ($contract->default_service_commission_value ?? 0) > 0) {
            $otherPct = (float) $contract->default_service_commission_value;
        }

        if ($otherPct > 0) {
            if ($isPermanentTarget && $contract->has_target) {
                $targetOtherPct = (float) ($contract->target_achieved_other_percentage ?? 0);
                $appliedPct = ($targetOtherPct > 0) ? $targetOtherPct : $otherPct;
                return round($itemTotal * ($appliedPct / 100), 2);
            }

            return round($itemTotal * ($otherPct / 100), 2);
        }

        return 0.00;
    }
}
