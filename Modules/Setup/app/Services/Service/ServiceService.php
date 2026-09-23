<?php

namespace Modules\Setup\Services\Service;

use Modules\Setup\Models\Service;
use Modules\Setup\Filters\Service\ServiceFilter;
use Modules\Setup\Http\Resources\Service\ServiceResource;
use Modules\Setup\Enums\ServiceTypeEnum;
use App\Support\API;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    public function index($request, ServiceFilter $filter)
    {
        $perPage = $request->get('per_page', 10);
        $query = Service::with(['department', 'items'])
            ->filter($filter)
            ->reorder()
            ->orderBy('id', 'desc');

        // جلب كل الخدمات بدون باجينيشن إذا تم تمرير per_page = -1 أو all=true أو paginate=false
        $data = ($request->boolean('all') || $request->get('paginate') === 'false' || (string) $perPage === '-1')
            ? $query->get()
            : $query->paginate((int) $perPage);

        return API::newInstance()
            ->isOk('Data retrieved successfully')
            ->setData(ServiceResource::collection($data))
            ->build();
    }

    public function store($request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            // 1. إنشاء الخدمة الأساسية
            $service = Service::create($validated);

            // 2. ربط المنتجات بجدول service_items للخدمات التي تقبل مواد مخزنية (session أو device)
            $serviceType = $service->type instanceof ServiceTypeEnum ? $service->type : ServiceTypeEnum::tryFrom($service->type);

            if ($serviceType !== ServiceTypeEnum::CONSULTATION && !empty($validated['items'])) {
                $service->items()->detach();
                foreach ($validated['items'] as $item) {
                    $service->items()->attach($item['item_id'], [
                        'quantity' => $item['quantity'],
                        'price'    => $item['price'] ?? 0,
                    ]);
                }
            }

            return API::newInstance()
                ->isCreated('Created successfully')
                ->setData(new ServiceResource($service->load(['department', 'items'])))
                ->build();
        });
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
        return DB::transaction(function () use ($id, $request) {
            $record = Service::findOrFail($id);
            $validated = $request->validated();

            // 1. تحديث بيانات الخدمة الأساسية
            $record->update($validated);

            // 2. تحديث الربط بالمخزن
            $serviceType = $record->type instanceof ServiceTypeEnum ? $record->type : ServiceTypeEnum::tryFrom($record->type);

            if ($serviceType === ServiceTypeEnum::CONSULTATION) {
                // لو تحولت الخدمة لكشف، يتم تفريغ أي مواد مخزنية مرتبطة بها
                $record->items()->detach();
            } elseif (array_key_exists('items', $validated)) {
                // لو تم إرسال items صراحة مع الجلسة أو الجهاز
                $record->items()->detach();
                if (!empty($validated['items'])) {
                    foreach ($validated['items'] as $item) {
                        $record->items()->attach($item['item_id'], [
                            'quantity' => $item['quantity'],
                            'price'    => $item['price'] ?? 0,
                        ]);
                    }
                }
            }

            return API::newInstance()
                ->isOk('Updated successfully')
                ->setData(new ServiceResource($record->load(['department', 'items'])))
                ->build();
        });
    }

    public function destroy($id)
    {
        $record = Service::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
