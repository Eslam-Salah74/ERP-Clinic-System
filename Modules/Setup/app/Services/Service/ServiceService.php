<?php

namespace Modules\Setup\Services\Service;

use Modules\Setup\Models\Service;
use Modules\Setup\Filters\Service\ServiceFilter;
use Modules\Setup\Http\Resources\Service\ServiceResource;
use Modules\Setup\Enums\ServiceTypeEnum;
use App\Support\API;

class ServiceService
{
    public function index($request, ServiceFilter $filter)
    {
        // إضافة with('items') لعرض المنتجات المرتبطة بالجلسات إن وجدت
        $data = Service::with(['department', 'items'])
            ->filter($filter)
            ->reorder()
            ->orderBy('id', 'desc')
            ->paginate(10);

        return API::newInstance()
            ->isOk('Data retrieved successfully')
            ->setData(ServiceResource::collection($data))
            ->build();
    }

    public function store($request)
    {
        // 1. إنشاء الخدمة الأساسية بالبيانات المُتحقق منها
        $service = Service::create($request->validated());

        // 2. ربط المنتجات بجدول service_items لو الخدمة مش كشف (consultation) وتم إرسال items
        if ($service->type !== ServiceTypeEnum::CONSULTATION && $request->has('items') && !empty($request->items)) {
            $syncData = [];
            foreach ($request->items as $item) {
                $syncData[$item['item_id']] = ['quantity' => $item['quantity']];
            }
            $service->items()->sync($syncData);
        }

        return API::newInstance()
            ->isCreated('Created successfully')
            ->setData(new ServiceResource($service->load(['department', 'items'])))
            ->build();
    }

    public function show($id)
    {
        // جلب الخدمة مع قسمها والمنتجات المرتبطة بها في المخزن
        $record = Service::with(['department', 'items'])->find($id);

        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }

        return API::newInstance()
            ->isOk('Data retrieved successfully')
            ->setData(new ServiceResource($record))
            ->build();
    }

    public function update($id, $request)
    {
        $record = Service::findOrFail($id);

        // 1. تحديث بيانات الخدمة الأساسية
        $record->update($request->validated());

        // 2. تحديث الربط بالمخزن (لو تم إرسال items جديدة)
        if ($record->type !== ServiceTypeEnum::CONSULTATION && $request->has('items')) {
            $syncData = [];
            foreach ($request->items as $item) {
                $syncData[$item['item_id']] = ['quantity' => $item['quantity']];
            }
            // sync هتقوم بالواجب: تمسح القديم وتزود الجديد أوتوماتيك
            $record->items()->sync($syncData);
        } else {
            // لو اتحولت لكشف أو مبعتش items، بنفضي جدول الربط بتاعها احتياطياً
            $record->items()->detach();
        }

        return API::newInstance()
            ->isOk('Updated successfully')
            ->setData(new ServiceResource($record->load(['department', 'items'])))
            ->build();
    }

    public function destroy($id)
    {
        $record = Service::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
