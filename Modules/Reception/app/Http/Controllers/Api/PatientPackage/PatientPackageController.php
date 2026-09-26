<?php

namespace Modules\Reception\Http\Controllers\Api\PatientPackage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Reception\Filters\PatientPackage\PatientPackageFilter;
use Modules\Reception\Http\Requests\PatientPackage\ConsumePackageRequest;
use Modules\Reception\Http\Requests\PatientPackage\PayPackageDebtRequest;
use Modules\Reception\Http\Requests\PatientPackage\SubscribePackageRequest;
use Modules\Reception\Services\PatientPackage\PatientPackageService;

class PatientPackageController extends Controller implements HasMiddleware
{
    protected $patientPackageService;

    public function __construct(PatientPackageService $patientPackageService)
    {
        $this->patientPackageService = $patientPackageService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read patient_packages', only: ['index', 'patientActivePackages']),
            new Middleware('permission:show patient_packages', only: ['show']),
            new Middleware('permission:create patient_packages', only: ['subscribe']),
            new Middleware('permission:update patient_packages', only: ['consume', 'payDebt']),
        ];
    }

    public function index(Request $request, PatientPackageFilter $filter)
    {
        return $this->patientPackageService->index($request, $filter);
    }

    public function show($id)
    {
        return $this->patientPackageService->show($id);
    }

    public function subscribe(SubscribePackageRequest $request)
    {
        return $this->patientPackageService->subscribe($request);
    }

    public function consume(ConsumePackageRequest $request)
    {
        return $this->patientPackageService->consume($request);
    }

    public function payDebt($id, PayPackageDebtRequest $request)
    {
        return $this->patientPackageService->payDebt($id, $request);
    }

    public function patientActivePackages($patientId)
    {
        return $this->patientPackageService->patientActivePackages($patientId);
    }
}
