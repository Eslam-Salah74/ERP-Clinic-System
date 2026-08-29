<?php

namespace Modules\Inventory\Http\Controllers\Api\Item;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Inventory\Services\Item\ItemService;
use Modules\Inventory\Filters\Item\ItemFilter;
use Modules\Inventory\Http\Requests\Item\StoreItemRequest;
use Modules\Inventory\Http\Requests\Item\UpdateItemRequest;

class ItemController extends Controller implements HasMiddleware
{
    protected $item;

    public function __construct(ItemService $item)
    {
        $this->item = $item;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read items', only: ['index']),
            new Middleware('permission:show items', only: ['show']),
            new Middleware('permission:create items', only: ['store']),
            new Middleware('permission:update items', only: ['update']),
            new Middleware('permission:delete items', only: ['destroy']),
        ];
    }

    public function index(Request $request, ItemFilter $filter)
    {
        return $this->item->index($request, $filter);
    }

    public function store(StoreItemRequest $request)
    {
        return $this->item->store($request);
    }

    public function show($item)
    {
        return $this->item->show($item);
    }

    public function update($item, UpdateItemRequest $request)
    {
        return $this->item->update($item, $request);
    }

    public function destroy($item)
    {
        return $this->item->destroy($item);
    }
}