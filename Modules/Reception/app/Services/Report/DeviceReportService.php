<?php

namespace Modules\Reception\Services\Report;

use App\Support\API;
use Carbon\Carbon;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseInvoiceItem;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\InvoiceItem;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Service;

class DeviceReportService
{
    /**
     * تقرير أداء وربحية الأجهزة التفصيلي بالفلاتر (الدخل + استهلاك المخزن + عمولة الطبيب + صافي الربح)
     */
    public function getDevicesReport($request)
    {
        $startDate    = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate      = Carbon::parse($request->input('end_date'))->endOfDay();
        $deviceId     = $request->input('device_id') ?? $request->input('service_id');
        $departmentId = $request->input('department_id');
        $doctorId     = $request->input('doctor_id');

        // جلب خدمات الأجهزة (نوع device أو الخدمات التي تحتوي على مواد مخزنية service_items)
        $devicesQuery = Service::with(['department', 'items'])
            ->where(function ($q) {
                $q->where('type', ServiceTypeEnum::DEVICE)
                  ->orWhereHas('items');
            });

        if ($deviceId) {
            $devicesQuery->where('id', $deviceId);
        }

        if ($departmentId) {
            $devicesQuery->where('department_id', $departmentId);
        }

        $deviceServices = $devicesQuery->get();

        if ($deviceServices->isEmpty()) {
            return API::newInstance()->isError('لا توجد أجهزة أو خدمات مطابقة لمحددات البحث.')->build();
        }

        $serviceIds = $deviceServices->pluck('id')->toArray();

        // جلب بنود الفواتير المرتبطة بهذه الأجهزة خلال الفترة المحددة
        $invoiceItemsQuery = InvoiceItem::with([
            'invoice.doctor.activeContract.serviceCommissions',
            'invoice.nurse.activeContract',
            'invoice.patient',
            'service.department'
        ])
            ->whereIn('service_id', $serviceIds)
            ->whereHas('invoice', function ($q) use ($startDate, $endDate, $doctorId) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value);

                if ($doctorId) {
                    $q->where('doctor_id', $doctorId);
                }
            });

        $invoiceItems = $invoiceItemsQuery->get();

        // تحميل أحدث أسعار الشراء للأصناف المخزنية لحساب التكلفة بدقة
        $itemCosts = $this->loadItemsCosts();

        $devicesReport = [];
        $totalDevicesRevenueAll     = 0.00;
        $totalConsumablesCostAll    = 0.00;
        $totalCommissionsAll        = 0.00;
        $totalDevicesNetProfitAll   = 0.00;
        $totalSessionsCountAll      = 0;

        foreach ($deviceServices as $device) {
            $itemsForThisDevice = $invoiceItems->where('service_id', $device->id);

            $sessionsCount = (int) $itemsForThisDevice->sum('quantity');
            $deviceRevenue = (float) $itemsForThisDevice->sum('total_price');
            $uniquePatients = $itemsForThisDevice->map(fn($item) => $item->invoice?->patient_id)->filter()->unique()->count();

            // 1. حساب المستلزمات الطبية والمواد المستهلكة من المخزن
            $consumablesSummary = [];
            $totalConsumablesCost = 0.00;

            foreach ($device->items as $item) {
                $qtyPerSession = (float) ($item->pivot->quantity ?? 1);
                $totalQtyConsumed = round($qtyPerSession * $sessionsCount, 2);

                // تكلفة الوحدة: سعر pivot إن وجد، وإلا سعر الشراء في المخزن، وإلا سعر البيع
                $unitCost = 0.00;
                if (isset($item->pivot->price) && (float) $item->pivot->price > 0) {
                    $unitCost = (float) $item->pivot->price;
                } elseif (isset($itemCosts[$item->id])) {
                    $unitCost = (float) $itemCosts[$item->id];
                } else {
                    $unitCost = (float) $item->selling_price;
                }

                $totalCostForThisItem = round($totalQtyConsumed * $unitCost, 2);
                $totalConsumablesCost += $totalCostForThisItem;

                $consumablesSummary[] = [
                    'item_id'                 => $item->id,
                    'item_name'               => $item->name,
                    'unit'                    => $item->stock_unit?->value ?? ($item->unit?->value ?? 'وحدة'),
                    'quantity_per_session'    => $qtyPerSession,
                    'total_quantity_consumed' => $totalQtyConsumed,
                    'unit_cost'               => round($unitCost, 2),
                    'total_cost'              => $totalCostForThisItem,
                ];
            }

            // 2. حساب عمولات الأطباء والتمريض على جلسات هذا الجهاز
            $totalCommissions = 0.00;
            $doctorsBreakdown = [];

            foreach ($itemsForThisDevice as $invItem) {
                $inv = $invItem->invoice;
                if (!$inv) continue;

                $doc = $inv->doctor;
                $docCommAmount = 0.00;

                if ($doc && $doc->activeContract) {
                    $docCommAmount = $this->calculateDoctorCommissionForItem($invItem, $doc->activeContract, (bool) ($doc->achieved_target ?? false));
                    $totalCommissions += $docCommAmount;

                    if (!isset($doctorsBreakdown[$doc->id])) {
                        $doctorsBreakdown[$doc->id] = [
                            'doctor_id'         => $doc->id,
                            'doctor_name'       => $doc->name,
                            'sessions_count'    => 0,
                            'revenue_generated' => 0.00,
                            'commissions_earned'=> 0.00,
                        ];
                    }
                    $doctorsBreakdown[$doc->id]['sessions_count']     += (int) $invItem->quantity;
                    $doctorsBreakdown[$doc->id]['revenue_generated'] += (float) $invItem->total_price;
                    $doctorsBreakdown[$doc->id]['commissions_earned']+= $docCommAmount;
                }

                // عمولة الممرضة على جلسة الجهاز إن وجدت في عقد التمريض
                if ($inv->nurse && $inv->nurse->activeContract) {
                    $nurseContract = $inv->nurse->activeContract;
                    $nurseRate = (float) ($nurseContract->device_session_commission ?? 0);
                    if ($nurseRate > 0) {
                        $nurseComm = ((int) $invItem->quantity * $nurseRate);
                        $totalCommissions += $nurseComm;
                    }
                }
            }

            // 3. صافي أرباح الجهاز للمركز (كسب كام بعد المستهلكات والعمولات)
            $deviceRevenue        = round($deviceRevenue, 2);
            $totalConsumablesCost = round($totalConsumablesCost, 2);
            $totalCommissions     = round($totalCommissions, 2);
            $deviceNetProfit      = round($deviceRevenue - $totalConsumablesCost - $totalCommissions, 2);

            $profitMargin = ($deviceRevenue > 0)
                ? round(($deviceNetProfit / $deviceRevenue) * 100, 2)
                : 0.00;

            $costPerSession   = ($sessionsCount > 0) ? round($totalConsumablesCost / $sessionsCount, 2) : 0.00;
            $profitPerSession = ($sessionsCount > 0) ? round($deviceNetProfit / $sessionsCount, 2) : 0.00;

            $totalDevicesRevenueAll    += $deviceRevenue;
            $totalConsumablesCostAll   += $totalConsumablesCost;
            $totalCommissionsAll       += $totalCommissions;
            $totalDevicesNetProfitAll  += $deviceNetProfit;
            $totalSessionsCountAll     += $sessionsCount;

            $narrative = "دخل الجهاز ({$deviceRevenue} ج) واستهلك مستلزمات من المخزن بقيمة ({$totalConsumablesCost} ج) وعمولات أطباء وتمريض ({$totalCommissions} ج) ليحقق صافي ربح فعلي للمركز قدره ({$deviceNetProfit} ج) بنسبة ربحية ({$profitMargin}%).";

            $devicesReport[] = [
                'device_id'              => $device->id,
                'device_name'            => $device->name,
                'department_id'          => $device->department_id,
                'department_name'        => $device->department?->name ?? 'عام',
                'service_price'          => (float) $device->price,
                'summary_narrative'      => $narrative,
                'activity_metrics'       => [
                    'sessions_count'         => $sessionsCount,
                    'unique_patients_count'  => $uniquePatients,
                    'cost_per_session'       => $costPerSession,
                    'profit_per_session'     => $profitPerSession,
                ],
                'financial_performance'  => [
                    'gross_revenue'          => $deviceRevenue,        // دخل الجهاز للمركز
                    'consumables_cost'       => $totalConsumablesCost,  // استهلك منتجات بكام
                    'commissions_paid'       => $totalCommissions,      // الطبيب والتمريض خدوا كام
                    'net_profit'             => $deviceNetProfit,       // صافي ربح المركز بعد العمولة والمستهلكات
                    'profit_margin_percent'  => $profitMargin,          // نسبة الربح %
                    'status'                 => $deviceNetProfit >= 0 ? 'profitable' : 'loss',
                ],
                'consumables_consumed'   => $consumablesSummary,       // تفصيل استهلاك المخزن صنفاً صنفاً
                'doctors_performance'    => array_values($doctorsBreakdown), // تفصيل الأطباء الذين عملوا على الجهاز
            ];
        }

        // ترتيب الأجهزة حسب الأكثر ربحية للمركز
        usort($devicesReport, fn($a, $b) => $b['financial_performance']['net_profit'] <=> $a['financial_performance']['net_profit']);

        $overallMargin = ($totalDevicesRevenueAll > 0)
            ? round(($totalDevicesNetProfitAll / $totalDevicesRevenueAll) * 100, 2)
            : 0.00;

        $isSingleDevice = ($deviceId && count($devicesReport) === 1);

        return API::newInstance()
            ->isOk('تم توليد تقرير أداء وربحية الأجهزة والمستهلكات بنجاح')
            ->setData([
                'report_title'       => $isSingleDevice ? ('تقرير أداء وربحية جهاز: ' . $devicesReport[0]['device_name']) : 'تقرير أداء وربحية أجهزة المركز والمستهلكات وصافي الأرباح',
                'period'             => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $startDate->diffInDays($endDate) + 1,
                    'preset'     => $request->input('period_preset', 'custom'),
                ],
                'is_single_device'   => $isSingleDevice,
                'aggregate_summary'  => [
                    'total_devices_count'     => count($devicesReport),
                    'total_sessions_count'    => $totalSessionsCountAll,
                    'total_gross_revenue'     => round($totalDevicesRevenueAll, 2),
                    'total_consumables_cost'  => round($totalConsumablesCostAll, 2),
                    'total_commissions_paid'  => round($totalCommissionsAll, 2),
                    'total_net_profit'        => round($totalDevicesNetProfitAll, 2),
                    'overall_profit_margin'   => $overallMargin,
                ],
                'devices'            => $isSingleDevice ? $devicesReport[0] : $devicesReport,
            ])
            ->build();
    }

    /**
     * حساب عمولة الطبيب على بند الخدمة المحدد
     */
    protected function calculateDoctorCommissionForItem(InvoiceItem $item, $contract, bool $isPermanentTarget): float
    {
        $service = $item->service;
        if (!$service || !$contract) {
            return 0.00;
        }

        $itemTotal = (float) $item->total_price;
        $qty       = (int) $item->quantity;

        // 1. عمولة مخصصة للخدمة في عقد الطبيب (serviceCommissions)
        $specificComm = $contract->serviceCommissions?->firstWhere('service_id', $service->id);
        if ($specificComm) {
            $isFixedAmount = ($specificComm->commission_type === CommissionTypeEnum::FIXED || $specificComm->commission_type === CommissionTypeEnum::FIXED->value);

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

        // 2. نسبة الخدمات العامة من العقد
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
