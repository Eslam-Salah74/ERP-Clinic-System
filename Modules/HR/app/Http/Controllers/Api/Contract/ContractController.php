<?php

namespace Modules\HR\Http\Controllers\Api\Contract;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\HR\Filters\Contract\StaffContractFilter;
use Modules\HR\Http\Requests\Contract\StoreStaffContractRequest;
use Modules\HR\Http\Requests\Contract\UpdateStaffContractRequest;
use Modules\HR\Services\Contract\ContractService;

class ContractController extends Controller implements HasMiddleware
{
    protected $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read staff_contracts', only: ['index', 'show', 'getActiveContractByUser']),
            new Middleware('permission:create staff_contracts', only: ['store']),
            new Middleware('permission:update staff_contracts', only: ['update']),
            new Middleware('permission:delete staff_contracts', only: ['destroy']),
        ];
    }

    public function index(Request $request, StaffContractFilter $filter)
    {
        return $this->contractService->index($request, $filter);
    }

    public function store(StoreStaffContractRequest $request)
    {
        return $this->contractService->store($request);
    }

    public function show($id)
    {
        return $this->contractService->show($id);
    }

    public function update($id, UpdateStaffContractRequest $request)
    {
        return $this->contractService->update($id, $request);
    }

    public function destroy($id)
    {
        return $this->contractService->destroy($id);
    }

    public function getActiveContractByUser($userId)
    {
        return $this->contractService->getActiveContractByUser($userId);
    }
}
