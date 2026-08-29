<?php

namespace Modules\Inventory\Services\Item;

use Modules\Inventory\Models\Item;
use Modules\Inventory\Filters\Item\ItemFilter;
use Modules\Inventory\Http\Resources\Item\ItemResource;
use App\Support\API;

class ItemService
{
    public function index($request, ItemFilter $filter)
    {
        // إضافة reorder() لضمان عمل latest() بشكل سليم وعرض الأحدث أولاً
        $data = Item::filter($filter)->reorder()->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(ItemResource::collection($data))->build();
    }

    public function store($request)
    {
        $data = Item::create($request->validated());
        return API::newInstance()->isCreated('Created successfully')->setData(new ItemResource($data))->build();
    }

    public function show($id)
    {
        $record = Item::find($id);
        if (!$record) return API::newInstance()->isError('Record not found')->build();
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new ItemResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Item::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new ItemResource($record))->build();
    }

    public function destroy($id)
    {
        $record = Item::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
