<?php

namespace Modules\Reception\Services\Report;

use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\InvoiceItem;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;

class DepartmentReportService
{
    /**
     * تقرير أداء الأقسام الشامل وتحديد أعلى 3 دكاترة في كل قسم
     */
    public function getDepartmentsReport($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $deptId    = $request->input('department_id');

        // جلب الأقسام
        $deptQuery = Department::query();
        if ($deptId) {
            $deptQuery->where('id', $deptId);
        }
        $departments = $deptQuery->get();

        if ($departments->isEmpty()) {
            return API::newInstance()->isError('القسم المحدد غير موجود.')->build();
        }

        // جلب كل فواتير الفترة النشطة (غير الملغاة) مع بنودها وخدماتها وأطبائها وعقودهم
        $invoices = Invoice::with([
            'doctor.activeContract.serviceCommissions',
            'items.service.department',
            'patient'
        ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value)
            ->get();

        // إجمالي دخل المركز في هذه الفترة لحساب نسب الأقسام بدقة
        $totalClinicRevenue = (float) $invoices->sum('grand_total');

        $departmentsData = [];
        $totalConsultationsAll = 0;
        $totalServicesAll      = 0;
        $totalDevicesAll       = 0;

        foreach ($departments as $dept) {
            $deptRevenue      = 0.00;
            $consultationsRev = 0.00;
            $servicesRev      = 0.00;
            $devicesRev       = 0.00;

            $consultationsCount = 0;
            $servicesCount      = 0;
            $devicesCount       = 0;

            $deptInvoicesIds = [];
            $deptPatientsIds = [];

            // تتبع دخل كل طبيب داخل هذا القسم لتحديد أعلى 3
            // المفتاح: doctor_id
            $doctorsStats = [];

            foreach ($invoices as $inv) {
                $hasItemsInDept = false;

                foreach ($inv->items as $item) {
                    if ($item->item_type !== 'service' || !$item->service) {
                        continue;
                    }

                    // التحقق هل الخدمة تابعة لهذا القسم
                    if ((int) $item->service->department_id === (int) $dept->id) {
                        $hasItemsInDept = true;
                        $itemTotal = (float) $item->total_price;
                        $itemQty   = (int) $item->quantity;

                        $deptRevenue += $itemTotal;

                        // تصنيف الخدمة
                        if ($item->service->type === ServiceTypeEnum::CONSULTATION) {
                            $consultationsCount += $itemQty;
                            $consultationsRev   += $itemTotal;
                            $totalConsultationsAll += $itemQty;
                        } elseif ($item->service->type === ServiceTypeEnum::DEVICE) {
                            $devicesCount += $itemQty;
                            $devicesRev   += $itemTotal;
                            $totalDevicesAll += $itemQty;
                        } else {
                            $servicesCount += $itemQty;
                            $servicesRev   += $itemTotal;
                            $totalServicesAll += $itemQty;
                        }

                        // تتبع إحصائيات الطبيب الذي أجرى الخدمة
                        $doctor = $inv->doctor;
                        if ($doctor) {
                            $docId = $doctor->id;
                            if (!isset($doctorsStats[$docId])) {
                                $doctorsStats[$docId] = [
                                    'doctor_id'            => $docId,
                                    'doctor_name'          => $doctor->name,
                                    'doctor_phone'         => $doctor->phone,
                                    'consultations_count'  => 0,
                                    'consultations_revenue'=> 0.00,
                                    'services_count'       => 0,
                                    'services_revenue'     => 0.00,
                                    'devices_count'        => 0,
                                    'devices_revenue'      => 0.00,
                                    'total_revenue'        => 0.00,
                                    'total_cases_count'    => 0,
                                    'doctor_commission'    => 0.00,
                                    'clinic_net_share'     => 0.00,
                                    'contract'             => $doctor->activeContract,
                                ];
                            }

                            $doctorsStats[$docId]['total_revenue']     += $itemTotal;
                            $doctorsStats[$docId]['total_cases_count'] += $itemQty;

                            if ($item->service->type === ServiceTypeEnum::CONSULTATION) {
                                $doctorsStats[$docId]['consultations_count']   += $itemQty;
                                $doctorsStats[$docId]['consultations_revenue'] += $itemTotal;
                            } elseif ($item->service->type === ServiceTypeEnum::DEVICE) {
                                $doctorsStats[$docId]['devices_count']   += $itemQty;
                                $doctorsStats[$docId]['devices_revenue'] += $itemTotal;
                            } else {
                                $doctorsStats[$docId]['services_count']   += $itemQty;
                                $doctorsStats[$docId]['services_revenue'] += $itemTotal;
                            }

                            // حساب عمولة الطبيب التقديرية لهذه الخدمة من العقد إن وجد
                            $commAmount = $this->calculateItemCommission($item, $doctor->activeContract);
                            $doctorsStats[$docId]['doctor_commission'] += $commAmount;
                        }
                    }
                }

                if ($hasItemsInDept) {
                    $deptInvoicesIds[] = $inv->id;
                    if ($inv->patient_id) {
                        $deptPatientsIds[] = $inv->patient_id;
                    }
                }
            }

            // معالجة وحساب عمولات وصافي المركز لكل طبيب وترتيبهم لتحديد أعلى 3
            $rankedDoctors = collect($doctorsStats)->map(function ($doc) {
                $docComm   = round($doc['doctor_commission'], 2);
                $totRev    = round($doc['total_revenue'], 2);
                $clinicNet = round(max(0, $totRev - $docComm), 2);

                unset($doc['contract']); // إزالة أوبجكت العقد لتنظيف المخرجات

                $doc['doctor_commission'] = $docComm;
                $doc['clinic_net_share']  = $clinicNet;
                $doc['consultations_revenue'] = round($doc['consultations_revenue'], 2);
                $doc['services_revenue']      = round($doc['services_revenue'], 2);
                $doc['devices_revenue']       = round($doc['devices_revenue'], 2);
                $doc['total_revenue']         = $totRev;

                return $doc;
            })
            ->sortByDesc('total_revenue')
            ->values();

            // أعلى 3 أطباء في القسم مع تحديد ترتيبهم
            $top3Doctors = $rankedDoctors->take(3)->map(function ($doc, $index) {
                $rankTitles = [1 => 'المركز الأول 🥇', 2 => 'المركز الثاني 🥈', 3 => 'المركز الثالث 🥉'];
                $rank = $index + 1;
                $doc['rank'] = $rank;
                $doc['rank_badge'] = $rankTitles[$rank] ?? "المركز {$rank}";
                return $doc;
            })->all();

            $revenueSharePercentage = ($totalClinicRevenue > 0)
                ? round(($deptRevenue / $totalClinicRevenue) * 100, 2)
                : 0.00;

            $departmentsData[] = [
                'department_id'          => $dept->id,
                'department_name'        => $dept->name,
                'total_revenue'          => round($deptRevenue, 2),
                'revenue_share_percent'  => $revenueSharePercentage,
                'invoices_count'         => count(array_unique($deptInvoicesIds)),
                'patients_count'         => count(array_unique($deptPatientsIds)),
                'operations_summary'     => [
                    'consultations' => [
                        'count'   => $consultationsCount,
                        'revenue' => round($consultationsRev, 2),
                    ],
                    'services'      => [
                        'count'   => $servicesCount,
                        'revenue' => round($servicesRev, 2),
                    ],
                    'devices'       => [
                        'count'   => $devicesCount,
                        'revenue' => round($devicesRev, 2),
                    ],
                ],
                'top_3_doctors'          => $top3Doctors,
                'all_doctors_count'      => $rankedDoctors->count(),
            ];
        }

        // ترتيب الأقسام حسب الأكثر دخلاً
        usort($departmentsData, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return API::newInstance()
            ->isOk('تم توليد تقرير الأقسام وأعلى الأطباء بنجاح')
            ->setData([
                'report_title'            => 'تقرير إيرادات الأقسام وأعلى 3 أطباء في كل قسم',
                'period'                  => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $startDate->diffInDays($endDate) + 1,
                ],
                'clinic_overview'         => [
                    'total_clinic_revenue'   => round($totalClinicRevenue, 2),
                    'total_departments'      => count($departmentsData),
                    'total_consultations'    => $totalConsultationsAll,
                    'total_services'         => $totalServicesAll,
                    'total_devices_sessions' => $totalDevicesAll,
                ],
                'departments'             => $departmentsData,
            ])
            ->build();
    }

    /**
     * احتساب عمولة الطبيب على بند الخدمة بناءً على العقد
     */
    private function calculateItemCommission($item, $contract): float
    {
        if (!$contract) {
            return 0.00;
        }

        $service = $item->service;
        if (!$service) {
            return 0.00;
        }

        // 1. فحص وجود عمولة مخصصة في جدول contract_service_commissions
        $specificComm = $contract->serviceCommissions?->firstWhere('service_id', $service->id);
        if ($specificComm) {
            if ($specificComm->commission_type === CommissionTypeEnum::FIXED) {
                return (float) $specificComm->commission_value * (int) $item->quantity;
            } else {
                return (float) $item->total_price * ((float) $specificComm->commission_value / 100);
            }
        }

        // 2. إذا كانت الخدمة ليزر
        $isLaser = str_contains(mb_strtolower($service->name), 'ليزر') || str_contains(strtolower($service->name), 'laser');
        if ($isLaser && (float) $contract->laser_service_commission_percentage > 0) {
            return (float) $item->total_price * ((float) $contract->laser_service_commission_percentage / 100);
        }

        // 3. نسبة الخدمات الأخرى
        if ((float) $contract->other_service_commission_percentage > 0) {
            return (float) $item->total_price * ((float) $contract->other_service_commission_percentage / 100);
        }

        // 4. القيمة الافتراضية
        if ((float) $contract->default_service_commission_value > 0) {
            if ($contract->default_service_commission_type === CommissionTypeEnum::FIXED) {
                return (float) $contract->default_service_commission_value * (int) $item->quantity;
            } else {
                return (float) $item->total_price * ((float) $contract->default_service_commission_value / 100);
            }
        }

        return 0.00;
    }
}
