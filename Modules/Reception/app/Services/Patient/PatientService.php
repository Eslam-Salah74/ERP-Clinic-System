<?php

namespace Modules\Reception\Services\Patient;

use Modules\Reception\Models\Patient;
use Modules\Reception\Filters\Patient\PatientFilter;
use Modules\Reception\Http\Resources\Patient\PatientResource;
use Modules\Reception\Http\Resources\Patient\PatientProfileResource;
use App\Support\API;
use Illuminate\Support\Facades\Auth;

class PatientService
{
    public function index($request, PatientFilter $filter)
    {
        $perPage = (int) $request->get('per_page', 10);
        $withRelations = ['creator', 'invoices'];

        if ($request->boolean('with_history') || $request->boolean('include_details')) {
            $withRelations['appointments'] = fn($q) => $q->with(['doctor', 'service.items', 'shift'])->latest('appointment_date');
            $withRelations['invoices'] = fn($q) => $q->with(['doctor', 'items.service.items', 'shift', 'creator'])->latest();
            $withRelations['followUps'] = fn($q) => $q->with(['doctor', 'appointment', 'shift'])->latest('follow_up_date');
            $withRelations['packages'] = fn($q) => $q->with(['package.department', 'balances.service', 'balances.product'])->latest();
        }

        $query = Patient::with($withRelations)
            ->withCount(['appointments', 'invoices', 'followUps', 'packages'])
            ->filter($filter)
            ->latest();

        $data = ($request->boolean('all') || $request->get('paginate') === 'false' || (string) $perPage === '-1')
            ? $query->get()
            : $query->paginate((int) $perPage);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(PatientResource::collection($data))->build();
    }

    public function store($request)
    {
        $validated = $request->validated();
        // تسجيل الأيدي الخاص بالموظف الحالي المسجل دخول
        $validated['created_by'] = Auth::id();

        $data = Patient::create($validated);
        return API::newInstance()->isCreated('Created successfully')->setData(new PatientResource($data->load(['creator', 'appointments', 'invoices', 'followUps', 'packages'])))->build();
    }

    public function show($id)
    {
        $record = Patient::with([
            'creator',
            'appointments' => fn($q) => $q->with(['doctor', 'service.items', 'shift'])->latest('appointment_date'),
            'invoices' => fn($q) => $q->with(['doctor', 'items.service.items', 'shift', 'creator'])->latest(),
            'followUps' => fn($q) => $q->with(['doctor', 'appointment', 'shift'])->latest('follow_up_date'),
            'packages' => fn($q) => $q->with(['package.department', 'balances.service', 'balances.product', 'creator'])->latest(),
        ])
        ->withCount(['appointments', 'invoices', 'followUps', 'packages'])
        ->find($id);

        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new PatientResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Patient::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new PatientResource($record->load(['creator', 'appointments', 'invoices', 'followUps', 'packages'])))->build();
    }

    public function destroy($id)
    {
        $record = Patient::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }

    /**
     * ملف المريض الشامل (كل ما يخص المريض: خدمات، منتجات، باقات وعروض، فواتير، مدفوع، متبقي، مواعيد، متابعات)
     */
    public function profile($id)
    {
        $patient = Patient::with([
            'creator',
            'appointments' => fn($q) => $q->with(['doctor', 'service.items', 'creator', 'shift'])->latest('appointment_date'),
            'invoices' => fn($q) => $q->with([
                'doctor',
                'items.service.department',
                'items.product',
                'items.package.department',
                'items.patientPackage.package',
                'items.patientPackageBalance.service',
                'items.patientPackageBalance.product',
                'transactions.creator',
                'creator',
                'shift'
            ])->latest(),
            'followUps' => fn($q) => $q->with(['doctor', 'appointment', 'creator', 'shift'])->latest('follow_up_date'),
            'packages' => fn($q) => $q->with([
                'package.department',
                'invoice',
                'creator',
                'balances.service.department',
                'balances.product',
                'consumptions.doctor',
                'consumptions.nurse',
                'consumptions.invoice',
            ])->latest(),
        ])->find($id);

        if (!$patient) {
            return API::newInstance()->isError('Record not found', 404)->build();
        }

        return API::newInstance()
            ->isOk('Patient profile retrieved successfully')
            ->setData(new PatientProfileResource($patient))
            ->build();
    }
}
