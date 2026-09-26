<?php

namespace Modules\Setup\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Setup\Filters\Package\PackageFilter;
use Modules\Setup\Http\Requests\Package\StorePackageRequest;
use Modules\Setup\Http\Requests\Package\UpdatePackageRequest;
use Modules\Setup\Services\Package\PackageService;

class PackageController extends Controller implements HasMiddleware
{
    protected $packageService;

    public function __construct(PackageService $packageService)
    {
        $this->packageService = $packageService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read packages', only: ['index']),
            new Middleware('permission:show packages', only: ['show']),
            new Middleware('permission:create packages', only: ['store']),
            new Middleware('permission:update packages', only: ['update']),
            new Middleware('permission:delete packages', only: ['destroy']),
        ];
    }

    public function index(Request $request, PackageFilter $filter)
    {
        return $this->packageService->index($request, $filter);
    }

    public function store(StorePackageRequest $request)
    {
        return $this->packageService->store($request);
    }

    public function show($id)
    {
        return $this->packageService->show($id);
    }

    public function update($id, UpdatePackageRequest $request)
    {
        return $this->packageService->update($id, $request);
    }

    public function destroy($id)
    {
        return $this->packageService->destroy($id);
    }
}
