<?php

namespace Modules\HR\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\HR\Filters\Payroll\PayrollFilter;
use Modules\HR\Http\Requests\Payroll\GeneratePayrollRequest;
use Modules\HR\Http\Requests\Payroll\PayPayrollRequest;
use Modules\HR\Http\Requests\Payroll\UpdatePayrollRequest;
use Modules\HR\Services\Payroll\PayrollService;

class PayrollController extends Controller implements HasMiddleware
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read payrolls', only: ['index', 'show']),
            new Middleware('permission:create payrolls', only: ['generate', 'generateAll']),
            new Middleware('permission:update payrolls', only: ['update']),
            new Middleware('permission:delete payrolls', only: ['destroy']),
            new Middleware('permission:approve payrolls', only: ['approve']),
            new Middleware('permission:pay payrolls', only: ['pay']),
        ];
    }

    public function index(Request $request, PayrollFilter $filter)
    {
        return $this->payrollService->index($request, $filter);
    }

    public function generate(GeneratePayrollRequest $request)
    {
        return $this->payrollService->generate($request);
    }

    public function store(GeneratePayrollRequest $request)
    {
        return $this->payrollService->generate($request);
    }

    public function generateAll(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'required_without:start_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_without:month'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'required_with:start_date'],
        ]);

        return $this->payrollService->generateAll($validated);
    }

    public function show($id)
    {
        return $this->payrollService->show($id);
    }

    public function update($id, UpdatePayrollRequest $request)
    {
        return $this->payrollService->update($id, $request);
    }

    public function approve($id)
    {
        return $this->payrollService->approve($id);
    }

    public function pay($id, PayPayrollRequest $request)
    {
        return $this->payrollService->pay($id, $request);
    }

    public function destroy($id)
    {
        return $this->payrollService->destroy($id);
    }
}
