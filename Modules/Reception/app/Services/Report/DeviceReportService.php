<?php

namespace Modules\Reception\Services\Report;

use App\Support\API;
use Carbon\Carbon;
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
     * تقرير استخدام الأجهزة والمستهلكات والربحية (كميات المستهلكات، تكلفة المواد، دخل الجهاز، وصافي أرباحه)
     */
    public function getDevicesReport($request)
    {
        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        $endDate   = Carbon::parse($request->input('end_date'))->endOfDay();
        $serviceId = $request->input('service_id');

        // جلب خدمات الأجهزة (نوع device أو الخدمات التي تحتوي على مواد مخزنية service_items)
        $devicesQuery = Service::with(['department', 'items'])
            ->where(function ($q) {
                $q->where('type', ServiceTypeEnum::DEVICE)
                  ->orWhereHas('items');
            });

        if ($serviceId) {
            $devicesQuery->where('id', $serviceId);
        }

        $deviceServices = $devicesQuery->get();

        if ($deviceServices->isEmpty()) {
            return API::newInstance()->isError('لا توجد خدمات أجهزة معرفة في النظام.')->build();
        }

        $serviceIds = $deviceServices->pluck('id')->toArray();

        // جلب بنود الفواتير المرتبطة بهذه الأجهزة خلال الفترة
        $invoiceItems = InvoiceItem::with(['invoice.doctor.activeContract', 'invoice.nurse.activeContract', 'service'])
            ->whereIn('service_id', $serviceIds)
            ->whereHas('invoice', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->where('status', '!=', InvoiceStatusEnum::CANCELLED->value);
            })
            ->get();

        // أسعار الشراء للأصناف المخزنية لحساب التكلفة بدقة إذا لم تكن محددة في pivot
        $itemCosts = $this->loadItemsCosts();

        $devicesReport = [];
        $totalDevicesRevenueAll     = 0.00;
        $totalConsumablesCostAll    = 0.00;
        $totalDeviceCommissionsAll  = 0.00;
        $totalDevicesNetProfitAll   = 0.00;
        $totalSessionsCountAll      = 0;

        foreach ($deviceServices as $device) {
            $itemsForThisDevice = $invoiceItems->where('service_id', $device->id);

            $sessionsCount = (int) $itemsForThisDevice->sum('quantity');
            $deviceRevenue = (float) $itemsForThisDevice->sum('total_price');

            // 1. حساب المستلزمات الطبية والمواد المستهلكة
            $consumablesSummary = [];
            $totalConsumablesCost = 0.00;

            // المواد المرتبطة بالخدمة من جدول service_items
            foreach ($device->items as $item) {
                $qtyPerSession = (float) ($item->pivot->quantity ?? 1);
                $totalQtyConsumed = $qtyPerSession * $sessionsCount; // استخدم كميات قد إيه

                // تكلفة الوحدة: سعر pivot إن وجد، وإلا سعر الشراء في المخزن، وإلا سعر البيع
                $unitCost = 0.00;
                if (isset($item->pivot->price) && (float) $item->pivot->price > 0) {
                    $unitCost = (float) $item->pivot->price;
                } elseif (isset($itemCosts[$item->id])) {
                    $unitCost = (float) $itemCosts[$item->id];
                } else {
                    $unitCost = (float) $item->selling_price;
                }

                $totalCostForThisItem = round($totalQtyConsumed * $unitCost, 2); // بكام
                $totalConsumablesCost += $totalCostForThisItem;

                $consumablesSummary[] = [
                    'item_id'                => $item->id,
                    'item_name'              => $item->name,
                    'unit'                   => $item->stock_unit?->value ?? ($item->unit?->value ?? 'قطعة'),
                    'quantity_per_session'   => $qtyPerSession,
                    'total_quantity_consumed'=> $totalQtyConsumed, // استخدم كميات قد إيه
                    'unit_cost'              => round($unitCost, 2), // بكام تكلفة القطعة/المل
                    'total_cost'             => $totalCostForThisItem, // إجمالي تكلفة المستهلك
                ];
            }

            // 2. حساب العمولات المنصرفة على جلسات الجهاز (للممرضة أو الطبيب)
            $totalCommissions = 0.00;
            foreach ($itemsForThisDevice as $invItem) {
                $inv = $invItem->invoice;
                if (!$inv) continue;

                // عمولة الممرضة على جلسة الجهاز إن وجدت
                if ($inv->nurse && $inv->nurse->activeContract) {
                    $nurseContract = $inv->nurse->activeContract;
                    $nurseCommRate = (float) ($nurseContract->device_session_commission ?? 0);
                    if ($nurseCommRate > 0) {
                        $totalCommissions += ((int) $invItem->quantity * $nurseCommRate);
                    }
                }
            }

            // 3. صافي أرباح الجهاز (كسب كام)
            $deviceRevenue        = round($deviceRevenue, 2);
            $totalConsumablesCost = round($totalConsumablesCost, 2);
            $totalCommissions     = round($totalCommissions, 2);
            $deviceNetProfit      = round($deviceRevenue - $totalConsumablesCost - $totalCommissions, 2); // كسب كام

            $profitMargin = ($deviceRevenue > 0)
                ? round(($deviceNetProfit / $deviceRevenue) * 100, 2)
                : 0.00;

            $costPerSession   = ($sessionsCount > 0) ? round($totalConsumablesCost / $sessionsCount, 2) : 0.00;
            $profitPerSession = ($sessionsCount > 0) ? round($deviceNetProfit / $sessionsCount, 2) : 0.00;

            $totalDevicesRevenueAll    += $deviceRevenue;
            $totalConsumablesCostAll   += $totalConsumablesCost;
            $totalDeviceCommissionsAll += $totalCommissions;
            $totalDevicesNetProfitAll  += $deviceNetProfit;
            $totalSessionsCountAll     += $sessionsCount;

            $devicesReport[] = [
                'device_id'              => $device->id,
                'device_name'            => $device->name,
                'department_id'          => $device->department_id,
                'department_name'        => $device->department?->name ?? 'عام',
                'service_price'          => (float) $device->price,
                'activity_metrics'       => [
                    'sessions_count'         => $sessionsCount,
                    'cost_per_session'       => $costPerSession,
                    'profit_per_session'     => $profitPerSession,
                ],
                'financial_performance'  => [
                    'device_revenue'         => $deviceRevenue, // إجمالي دخل الجهاز
                    'total_consumables_cost' => $totalConsumablesCost, // إجمالي تكلفة المواد المستهلكة
                    'total_commissions_paid' => $totalCommissions, // عمولات جلسات الأجهزة
                    'device_net_profit'      => $deviceNetProfit, // كسب كام الجهاز
                    'profit_margin_percent'  => $profitMargin, // نسبة الربح %
                    'performance_status'     => $deviceNetProfit >= 0 ? 'profitable' : 'loss',
                    'status_note'            => $deviceNetProfit >= 0 ? "أرباح صافية بنسبة {$profitMargin}%" : "خسارة تشغيلية بقيمة " . abs($deviceNetProfit) . " ج",
                ],
                'consumables_consumed'   => $consumablesSummary, // كميات وأسعار المستهلكات
            ];
        }

        // ترتيب الأجهزة حسب الأكثر ربحاً
        usort($devicesReport, fn($a, $b) => $b['financial_performance']['device_net_profit'] <=> $a['financial_performance']['device_net_profit']);

        return API::newInstance()
            ->isOk('تم توليد تقرير الأجهزة والمستهلكات والربحية بنجاح')
            ->setData([
                'report_title'       => 'تقرير أداء الأجهزة واستهلاك المواد والمستلزمات الطبية وصافي الأرباح',
                'period'             => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'days_count' => $startDate->diffInDays($endDate) + 1,
                ],
                'devices_count'      => count($devicesReport),
                'aggregate_summary'  => [
                    'total_sessions_count'        => $totalSessionsCountAll,
                    'total_devices_revenue'       => round($totalDevicesRevenueAll, 2),
                    'total_consumables_cost'      => round($totalConsumablesCostAll, 2),
                    'total_commissions_paid'      => round($totalDeviceCommissionsAll, 2),
                    'total_devices_net_profit'    => round($totalDevicesNetProfitAll, 2),
                    'overall_devices_margin'      => $totalDevicesRevenueAll > 0 ? round(($totalDevicesNetProfitAll / $totalDevicesRevenueAll) * 100, 2) : 0.00,
                ],
                'devices'            => $devicesReport,
            ])
            ->build();
    }

    /**
     * تحميل متوسط أو أحدث سعر شراء لكل صنف مخزني
     */
    private function loadItemsCosts(): array
    {
        $costs = [];

        // جلب أحدث أسعار الشراء من فواتير المشتريات
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
