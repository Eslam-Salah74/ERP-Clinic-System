<?php

namespace Modules\HR\Http\Controllers\Api\CommissionTemplate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\HR\Http\Requests\CommissionTemplate\StoreCommissionTemplateRequest;
use Modules\HR\Http\Requests\CommissionTemplate\UpdateCommissionTemplateRequest;
use Modules\HR\Services\CommissionTemplate\CommissionTemplateService;

class CommissionTemplateController extends Controller implements HasMiddleware
{
    protected $templateService;

    public function __construct(CommissionTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read staff_contracts', only: ['index', 'show']),
            new Middleware('permission:create staff_contracts', only: ['store']),
            new Middleware('permission:update staff_contracts', only: ['update']),
            new Middleware('permission:delete staff_contracts', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        return $this->templateService->index($request);
    }

    public function store(StoreCommissionTemplateRequest $request)
    {
        return $this->templateService->store($request);
    }

    public function show($id)
    {
        return $this->templateService->show($id);
    }

    public function update($id, UpdateCommissionTemplateRequest $request)
    {
        return $this->templateService->update($id, $request);
    }

    public function destroy($id)
    {
        return $this->templateService->destroy($id);
    }
}

