<?php

namespace Modules\Reception\Services\PatientPackage;

use App\Enums\UserType;
use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PatientPackageStatusEnum;
use Modules\Reception\Filters\PatientPackage\PatientPackageFilter;
use Modules\Reception\Http\Requests\Invoice\StoreInvoiceRequest;
use Modules\Reception\Http\Resources\PatientPackage\PatientPackageResource;
use Modules\Reception\Models\PatientPackage;
use Modules\Reception\Models\PatientPackageBalance;
use Modules\Reception\Notifications\UnpaidPackageConsumptionAttemptNotification;
use Modules\Reception\Services\Invoice\InvoiceService;
use Modules\Setup\Models\Package;
use Modules\Setup\Models\Setting;

class PatientPackageService
{
    protected $invoiceService;

    /**
     * تتبع المحاولات التي تم إرسال إشعار بها أثناء نفس الـ Request لمنع التكرار
     */
    protected static array $notifiedAttempts = [];

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

        // التحقق من سقف الاستهلاك المسموح به في ظل وجود مديونية متبقية
        $limitError = self::checkUnpaidConsumptionLimit($balance, $qty);
        if ($limitError) {
            return API::newInstance()->isError($limitError)->build();
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

    /**
     * التحقق من الحد الأقصى لاستهلاك الباقة في حال وجود مديونية متبقية
     */
    public static function checkUnpaidConsumptionLimit(PatientPackageBalance $balance, float $requestedQty): ?string
    {
        $patientPackage = $balance->patientPackage;
        if (!$patientPackage) {
            return null;
        }

        $remainingAmount = (float) $patientPackage->remaining_amount;
        // إذا كانت الباقة مسددة بالكامل، لا يوجد أي حظر
        if ($remainingAmount <= 0) {
            return null;
        }

        $package = $patientPackage->package;
        $packageType = $package?->type instanceof \BackedEnum
            ? $package->type->value
            : ($package?->type ?? 'sessions');

        $settingKey = match ($packageType) {
            'pulses' => 'package_unpaid_consumption_limit_pulses',
            'units_volume' => 'package_unpaid_consumption_limit_units_volume',
            'mixed' => 'package_unpaid_consumption_limit_mixed',
            default => 'package_unpaid_consumption_limit_sessions',
        };

        $limitVal = Setting::where('key', $settingKey)->value('value');
        if ($limitVal === null || !is_numeric($limitVal)) {
            $limitVal = 70;
        }

        $maxAllowedPct = (float) $limitVal;

        // إجمالي كميات الباقة والمستهلك منها
        $totalPackageQty = (float) $patientPackage->balances()->sum('total_quantity');
        $currentConsumedQty = (float) $patientPackage->balances()->sum('consumed_quantity');
        $newTotalConsumed = $currentConsumedQty + $requestedQty;

        $overallPct = $totalPackageQty > 0 ? ($newTotalConsumed / $totalPackageQty) * 100 : 0;
        $currentPct = $totalPackageQty > 0 ? ($currentConsumedQty / $totalPackageQty) * 100 : 0;

        // فحص نسبة البند المحدد
        $balanceTotal = (float) $balance->total_quantity;
        $balanceConsumed = (float) $balance->consumed_quantity + $requestedQty;
        $balancePct = $balanceTotal > 0 ? ($balanceConsumed / $balanceTotal) * 100 : 0;
        $currentBalancePct = $balanceTotal > 0 ? ((float) $balance->consumed_quantity / $balanceTotal) * 100 : 0;

        $effectiveConsumptionPct = max($overallPct, $balancePct);
        $effectiveCurrentPct = max($currentPct, $currentBalancePct);

        if ($effectiveConsumptionPct > $maxAllowedPct || $effectiveCurrentPct >= $maxAllowedPct) {
            $remainingFormatted = number_format($remainingAmount, 2);
            $typeNameArabic = match ($packageType) {
                'pulses' => 'النبضات',
                'units_volume' => 'الكميات/الملي',
                'mixed' => 'المشتركة',
                default => 'الجلسات',
            };

            // إرسال إشعار للمديرين والموظف بمحاولة الاستهلاك في ظل وجود مديونية
            self::sendUnpaidConsumptionAttemptNotification(
                $patientPackage,
                $balance,
                $requestedQty,
                $maxAllowedPct,
                $effectiveConsumptionPct
            );

            return "عذراً، لقد تم الوصول إلى الحد المسموح به لاستهلاك باقة ({$typeNameArabic}) والمحدد بـ ({$maxAllowedPct}%) في ظل وجود متبقٍ غير مسدد. يجب سداد باقي المبلغ المتبقي عليك وقدره ({$remainingFormatted} ج) دفعة واحدة قبل استهلاك مزيد من الرصيد.";
        }

        return null;
    }

    /**
     * إرسال إشعار بمحاولة استهلاك باقة مقيدة بمديونية تجاوزت الحد المسموح به
     */
    public static function sendUnpaidConsumptionAttemptNotification(
        PatientPackage $patientPackage,
        PatientPackageBalance $balance,
        float $requestedQty,
        float $maxAllowedPct,
        float $effectivePct
    ): void {
        try {
            $employee = Auth::user();

            // منع تكرار الإشعار في نفس الـ Request
            $cacheKey = ($employee?->id ?? 'guest') . '_' . $patientPackage->id . '_' . $balance->id;
            if (isset(self::$notifiedAttempts[$cacheKey])) {
                return;
            }
            self::$notifiedAttempts[$cacheKey] = true;

            $patientPackage->loadMissing(['patient', 'package']);
            $balance->loadMissing(['service', 'product']);

            // المستلمون: المديرون ومسؤولو النظام
            $recipients = User::where('is_active', true)
                ->where(function ($q) {
                    $q->where('type', UserType::ADMIN->value)
                      ->orWhereHas('roles', function ($rq) {
                          $rq->whereIn('name', ['admin', 'super-admin', 'Admin', 'Super Admin']);
                      });
                })
                ->get();

            // إضافة الموظف القائم بالمحاولة إلى المستلمين ليكون على دراية بالإشعار
            if ($employee && !$recipients->contains('id', $employee->id)) {
                $recipients->push($employee);
            }

            foreach ($recipients as $recipient) {
                // منع تكرار نفس الإشعار لنفس المستلم خلال دقيقة واحدة لتجنب التكرار
                $alreadyNotified = $recipient->notifications()
                    ->whereJsonContains('data->type', 'package_debt_limit')
                    ->whereJsonContains('data->patient_package_id', $patientPackage->id)
                    ->whereJsonContains('data->employee_id', $employee?->id)
                    ->where('created_at', '>=', Carbon::now()->subMinutes(1))
                    ->exists();

                if (!$alreadyNotified) {
                    $recipient->notify(new UnpaidPackageConsumptionAttemptNotification(
                        $patientPackage,
                        $balance,
                        $employee,
                        $requestedQty,
                        $maxAllowedPct,
                        $effectivePct
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send unpaid package consumption attempt notification: ' . $e->getMessage());
        }
    }
}
