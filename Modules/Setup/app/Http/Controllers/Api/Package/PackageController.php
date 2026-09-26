<?php

namespace Modules\Setup\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Setup\Filters\Package\PackageFilter;
use Modules\Setup\Http\Requests\Package\StorePackageRequest;
use Modules\Setup\Http\Requests\Package\UpdatePackageRequest;
use Modules\Setup\Services\Package\PackageService;

class PackageController extends Controller
{
    protected $packageService;

    public function __construct(PackageService $packageService)
    {
        $this->packageService = $packageService;
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
