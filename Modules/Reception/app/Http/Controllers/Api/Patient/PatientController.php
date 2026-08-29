<?php

namespace Modules\Reception\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Reception\Services\Patient\PatientService;
use Modules\Reception\Filters\Patient\PatientFilter;
use Modules\Reception\Http\Requests\Patient\StorePatientRequest;
use Modules\Reception\Http\Requests\Patient\UpdatePatientRequest;

class PatientController extends Controller implements HasMiddleware
{
    protected $patient;

    public function __construct(PatientService $patient)
    {
        $this->patient = $patient;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read patients', only: ['index']),
            new Middleware('permission:show patients', only: ['show']),
            new Middleware('permission:create patients', only: ['store']),
            new Middleware('permission:update patients', only: ['update']),
            new Middleware('permission:delete patients', only: ['destroy']),
        ];
    }

    public function index(Request $request, PatientFilter $filter)
    {
        return $this->patient->index($request, $filter);
    }

    public function store(StorePatientRequest $request)
    {
        return $this->patient->store($request);
    }

    public function show($patient)
    {
        return $this->patient->show($patient);
    }

    public function update($patient, UpdatePatientRequest $request)
    {
        return $this->patient->update($patient, $request);
    }

    public function destroy($patient)
    {
        return $this->patient->destroy($patient);
    }
}