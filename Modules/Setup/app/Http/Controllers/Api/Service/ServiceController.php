<?php

namespace Modules\Setup\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Setup\Services\Service\ServiceService;
use Modules\Setup\Filters\Service\ServiceFilter;
use Modules\Setup\Http\Requests\Service\StoreServiceRequest;
use Modules\Setup\Http\Requests\Service\UpdateServiceRequest;

class ServiceController extends Controller implements HasMiddleware
{
    protected $service;

    public function __construct(ServiceService $service)
    {
        $this->service = $service;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read services', only: ['index']),
            new Middleware('permission:show services', only: ['show']),
            new Middleware('permission:create services', only: ['store']),
            new Middleware('permission:update services', only: ['update']),
            new Middleware('permission:delete services', only: ['destroy']),
        ];
    }

    public function index(Request $request, ServiceFilter $filter)
    {
        return $this->service->index($request, $filter);
    }

    public function store(StoreServiceRequest $request)
    {
        return $this->service->store($request);
    }

    public function show($service)
    {
        return $this->service->show($service);
    }

    public function update($service, UpdateServiceRequest $request)
    {
        return $this->service->update($service, $request);
    }

    public function destroy($service)
    {
        return $this->service->destroy($service);
    }
}