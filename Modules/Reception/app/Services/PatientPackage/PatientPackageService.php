<?php

namespace Modules\Reception\Services\PatientPackage;

use App\Support\API;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PatientPackageStatusEnum;
use Modules\Reception\Filters\PatientPackage\PatientPackageFilter;
use Modules\Reception\Http\Requests\Invoice\StoreInvoiceRequest;
use Modules\Reception\Http\Resources\PatientPackage\PatientPackageResource;
use Modules\Reception\Models\PatientPackage;
use Modules\Reception\Models\PatientPackageBalance;
use Modules\Reception\Services\Invoice\InvoiceService;
use Modules\Setup\Models\Package;

class PatientPackageService
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function index($request, PatientPackageFilter $filter)
    {
        $perPage = $request->get('per_page', 10);
        $query = PatientPackage::with(['patient', 'package.department', 'balances.service', 'balances.product', 'creator'])
            ->filter($filter)
            ->reorder()
            ->orderBy('id', 'desc');

        $data = ($request->boolean('all') || $request->get('paginate') === 'false' || (string) $perPage === '-1')
            ? $query->get()
            : $query->paginate((int) $perPage);

        return API::newInstance()
            ->isOk('Patient packages retrieved successfully')
            ->setData(PatientPackageResource::collection($data))
            ->build();
    }

    public function show($id)
    {
        $patientPackage = PatientPackage::with([
            'patient',
            'package.department',
            'invoice',
            'balances.service',
            'balances.product',
            'consumptions.doctor',
            'consumptions.nurse',
            'consumptions.invoice',
            'creator'
        ])->find($id);

        if (!$patientPackage) {
            return API::newInstance()->isError('Patient package not found')->build();
        }

        return API::newInstance()
            ->isOk('Patient package details retrieved successfully')
            ->setData(new PatientPackageResource($patientPackage))
            ->build();
    }

    protected function resolveValidated($request): array
    {
        if (is_array($request)) {
            return $request;
        }

        try {
            if (method_exists($request, 'validated')) {
                $val = $request->validated();
                if (!empty($val)) {
                    return $val;
                }
            }
        } catch (\Throwable $e) {
        }

        return $request->all();
    }

    /**
     * اشتراك المريض في باقة / عرض وإنشاء فاتورة بيع الباقة وحركة الخزنة
     */
    public function subscribe($request)
    {
        $validated = $this->resolveValidated($request);
        $package = Package::find($validated['package_id']);
        if (!$package || !$package->is_active) {
            return API::newInstance()->isError('الباقة المحددة غير متوفرة أو معطلة.')->build();
        }

        // بناء بيانات الفاتورة واستدعاء خدمة الفواتير الموحدة
        $invoiceRequestData = [
            'patient_id' => $validated['patient_id'],
            'type' => InvoiceTypeEnum::PACKAGE_SALE->value,
            'payment_method' => $validated['payment_method'],
            'doctor_id' => $validated['doctor_id'] ?? null,
            'paid_amount' => $validated['paid_amount'] ?? null,
            'notes' => $validated['notes'] ?? ('اشتراك في عرض: ' . $package->name),
            'items' => [
                [
                    'item_type' => 'package',
                    'package_id' => $package->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $invoiceResponse = $this->invoiceService->store($invoiceRequestData);
        $responseData = $invoiceResponse->getData(true);

        if (!isset($responseData['status']) || !$responseData['status']) {
            return $invoiceResponse;
        }

        // إرجاع الباقة المنشأة حديثاً للمريض مع أرصدتها
        $patientPackage = PatientPackage::with(['patient', 'package.department', 'balances.service', 'balances.product', 'invoice'])
            ->where('patient_id', $validated['patient_id'])
            ->where('package_id', $package->id)
            ->latest('id')
            ->first();

        return API::newInstance()
            ->isCreated('تم الاشتراك في العرض وإصدار الفاتورة وتوليد محفظة الأرصدة بنجاح.')
            ->setData(new PatientPackageResource($patientPackage))
            ->build();
    }

    /**
     * استهلاك جلسة أو كمية من باقة المريض وربطها بالطبيب المنفذ
     */
    public function consume($request)
    {
        $validated = $this->resolveValidated($request);
        $balance = PatientPackageBalance::with(['patientPackage.package', 'service'])->find($validated['patient_package_balance_id']);

        if (!$balance) {
            return API::newInstance()->isError('رصيد الباقة المحدد غير موجود.')->build();
        }

        $patientPackage = $balance->patientPackage;
        if ($patientPackage->status !== PatientPackageStatusEnum::ACTIVE) {
            return API::newInstance()->isError('هذه الباقة غير نشطة أو تم استهلاكها بالكامل بالفعل.')->build();
        }

        $qty = (float) $validated['quantity'];
        if ($qty > (float) $balance->remaining_quantity) {
            return API::newInstance()->isError("الرصيد المتبقي للبند ({$balance->remaining_quantity}) غير كافٍ لاستهلاك ({$qty}).")->build();
        }

        // إنشاء فاتورة جلسة بصافي 0 ج للخدمة المستهلكة من الباقة مع إثبات تنفيذ الطبيب
        $invoiceRequestData = [
            'patient_id' => $patientPackage->patient_id,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'doctor_id' => $validated['doctor_id'],
            'nurse_id' => $validated['nurse_id'] ?? null,
            'type' => InvoiceTypeEnum::SESSION->value,
            'payment_method' => 'cash', // لن يدفع شيئاً إذا كانت قيمة الفاتورة 0
            'paid_amount' => 0,
            'notes' => $validated['notes'] ?? ('استهلاك من باقة: ' . $patientPackage->package->name),
            'items' => [
                [
                    'item_type' => 'package_consumption',
                    'patient_package_balance_id' => $balance->id,
                    'service_id' => $balance->service_id,
                    'product_id' => $balance->product_id,
                    'quantity' => $qty,
                    'notes' => $validated['notes'] ?? null,
                ]
            ],
        ];

        $invoiceResponse = $this->invoiceService->store($invoiceRequestData);
        $responseData = $invoiceResponse->getData(true);

        if (!isset($responseData['status']) || !$responseData['status']) {
            return $invoiceResponse;
        }

        return API::newInstance()
            ->isOk('تم استهلاك الجلسة بنجاح، وخصمها من رصيد الباقة، وإثبات دور وعمولة الطبيب.')
            ->setData(new PatientPackageResource($patientPackage->fresh(['balances.service', 'balances.product', 'consumptions.doctor'])))
            ->build();
    }

    /**
     * سداد جزء من مديونية باقة عبر الشفت الحالي
     */
    public function payDebt($id, $request)
    {
        $patientPackage = PatientPackage::with('package')->find($id);
        if (!$patientPackage) {
            return API::newInstance()->isError('الباقة المحددة غير موجودة.')->build();
        }

        if ((float) $patientPackage->remaining_amount <= 0) {
            return API::newInstance()->isError('هذه الباقة مسددة بالكامل ولا توجد عليها أي مديونية.')->build();
        }

        $validated = $this->resolveValidated($request);
        $payAmount = (float) $validated['amount'];

        if ($payAmount > (float) $patientPackage->remaining_amount) {
            return API::newInstance()->isError("المبلغ المدخل ({$payAmount} ج) أكبر من إجمالي المتبقي على الباقة ({$patientPackage->remaining_amount} ج).")->build();
        }

        // إنشاء فاتورة تحصيل قسط باقة
        $invoiceRequestData = [
            'patient_id' => $patientPackage->patient_id,
            'type' => InvoiceTypeEnum::PACKAGE_SALE->value,
            'payment_method' => $validated['payment_method'],
            'paid_amount' => $payAmount,
            'notes' => $validated['notes'] ?? ('سداد قسط باقة: ' . $patientPackage->package->name),
            'items' => [
                [
                    'item_type' => 'package_debt_payment',
                    'patient_package_id' => $patientPackage->id,
                    'amount' => $payAmount,
                    'quantity' => 1,
                ]
            ],
        ];

        $invoiceResponse = $this->invoiceService->store($invoiceRequestData);
        $responseData = $invoiceResponse->getData(true);

        if (!isset($responseData['status']) || !$responseData['status']) {
            return $invoiceResponse;
        }

        return API::newInstance()
            ->isOk('تم تحصيل المبلغ وإيداعه في الخزنة وتخفيض مديونية الباقة بنجاح.')
            ->setData(new PatientPackageResource($patientPackage->fresh(['balances.service', 'balances.product'])))
            ->build();
    }

    /**
     * جلب الباقات المتاحة للمريض الحالي لاختيارها في الريسيبشن
     */
    public function patientActivePackages($patientId)
    {
        $packages = PatientPackage::with(['package.department', 'balances.service', 'balances.product'])
            ->where('patient_id', $patientId)
            ->where('status', PatientPackageStatusEnum::ACTIVE)
            ->get();

        return API::newInstance()
            ->isOk('Patient active packages retrieved')
            ->setData(PatientPackageResource::collection($packages))
            ->build();
    }
}
