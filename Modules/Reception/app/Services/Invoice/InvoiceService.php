<?php

namespace Modules\Reception\Services\Invoice;

use App\Support\API;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Item;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\InvoiceStatusEnum;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Filters\Invoice\InvoiceFilter;
use Modules\Reception\Http\Resources\Invoice\InvoiceResource;
use Modules\Reception\Models\Appointment;
use Modules\Reception\Models\Invoice;
use Modules\Reception\Models\Patient;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Setup\Models\Service;
use Modules\Setup\Models\Setting;
use Modules\Setup\Models\Package;
use Modules\Reception\Models\PatientPackage;
use Modules\Reception\Models\PatientPackageBalance;
use Modules\Reception\Models\PatientPackageConsumption;
use Modules\Reception\Enums\PatientPackageStatusEnum;
use Modules\Reception\Enums\VisitTypeEnum;

class InvoiceService
{
    public function index($request, InvoiceFilter $filter)
    {
        $data = Invoice::filter($filter)
            ->with(['items.service.items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])
            ->latest()
            ->paginate(10);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(InvoiceResource::collection($data))->build();
    }

    /**
     * إنشاء فاتورة جديدة مع المعالجة الذكية للحجوزات والمخزن والخزنة
     */
    public function store($request)
    {
        $validated = is_array($request) ? $request : (method_exists($request, 'validated') && $request->validated() ? $request->validated() : $request->all());
        $userId = Auth::id();

        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if (!$activeShift) {
            return API::newInstance()->isError('لا يمكنك إنشاء فاتورة. يجب فتح شفت أولاً.')->build();
        }

        try {
            return DB::transaction(function () use ($validated, $activeShift, $userId) {

                $patientId = $validated['patient_id'];
                $invoiceType = $validated['type'];
                $appointmentId = $validated['appointment_id'] ?? null;
                $nurseId = $validated['nurse_id'] ?? null;
                $doctorId = $validated['doctor_id'] ?? null;
                $appointment = null;

                if ($invoiceType === InvoiceTypeEnum::DIRECT_SALE->value || $invoiceType === InvoiceTypeEnum::PACKAGE_SALE->value) {
                    $appointmentId = null;
                } else {
                    if (empty($appointmentId)) {
                        $todayAppointment = Appointment::where('patient_id', $patientId)
                            ->where('status', AppointmentStatusEnum::PENDING->value)
                            ->whereDate('appointment_date', today())
                            ->first();

                        if ($todayAppointment) {
                            $appointmentId = $todayAppointment->id;
                            $appointment = $todayAppointment;

                            $todayAppointment->update([
                                'status' => AppointmentStatusEnum::COMPLETED->value,
                                'nurse_id' => $nurseId ?? $todayAppointment->nurse_id,
                                'doctor_id' => $doctorId ?? $todayAppointment->doctor_id,
                                'shift_id' => $activeShift->id,
                            ]);

                            $doctorId = $todayAppointment->doctor_id;
                        } else {
                            $dummyAppointment = Appointment::create([
                                'patient_id' => $patientId,
                                'doctor_id' => $doctorId,
                                'nurse_id' => $nurseId,
                                'service_id' => $validated['items'][0]['service_id'] ?? null,
                                'service_items_ids' => $validated['items'][0]['service_items_ids'] ?? null,
                                'shift_id' => $activeShift->id,
                                'appointment_date' => now(),
                                'visit_type' => $invoiceType,
                                'status' => AppointmentStatusEnum::COMPLETED->value,
                                'notes' => 'حجز فوري (Walk-in) تم إنشاؤه واكتشافه تلقائياً مع الفاتورة',
                                'created_by' => $userId,
                            ]);

                            $appointmentId = $dummyAppointment->id;
                            $appointment = $dummyAppointment;
                        }
                    } else {
                        $appointment = Appointment::find($appointmentId);
                        if (!$appointment) {
                            throw new \Exception('الحجز المحدد غير موجود.');
                        }
                        $appointment->update([
                            'status' => AppointmentStatusEnum::COMPLETED->value,
                            'nurse_id' => $nurseId ?? $appointment->nurse_id,
                            'shift_id' => $activeShift->id,
                        ]);
                        $doctorId = $appointment->doctor_id;
                    }
                }

                $year = date('Y');
                $lastInvoice = Invoice::whereYear('created_at', $year)
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                $nextSequence = 1;
                if ($lastInvoice) {
                    $parts = explode('-', $lastInvoice->invoice_number);
                    $lastSeq = isset($parts[2]) ? intval($parts[2]) : 0;
                    $nextSequence = $lastSeq + 1;
                }
                $invoiceNumber = 'INV-' . $year . '-' . str_pad($nextSequence, 6, '0', STR_PAD_LEFT);

                $queueNumber = null;
                if (!empty($doctorId)) {
                    $lastQueue = Invoice::where('doctor_id', $doctorId)
                        ->where('shift_id', $activeShift->id)
                        ->lockForUpdate()
                        ->max('queue_number');
                    $queueNumber = $lastQueue ? $lastQueue + 1 : 1;
                }

                $subTotal = 0;
                $processedItems = [];
                foreach ($validated['items'] as $item) {
                    $itemName = '';
                    $unitPrice = 0;
                    $serviceItemsToDeduct = [];
                    $consumedIds = [];
                    $quantity = isset($item['quantity']) && !empty($item['quantity']) ? (int)$item['quantity'] : 1;

                    if ($item['item_type'] === 'service') {
                        $service = Service::with('items')->find($item['service_id']);
                        if (!$service) {
                            throw new \Exception('الخدمة المحددة غير موجودة في قاعدة البيانات.');
                        }
                        $itemName = $service->name;
                        $serviceBasePrice = (float) $service->price;

                        // تحديد الأصناف المرتبطة بالخدمة:
                        // 1. إذا تم تحديدها صراحة في العنصر service_items_ids
                        // 2. أو إذا كان الحجز المرتبط يحتوي على service_items_ids
                        // 3. أو جميع الأصناف المرتبطة بالخدمة كإعداد افتراضي
                        $chosenItemsIds = $item['service_items_ids'] ?? null;
                        if ($chosenItemsIds === null && isset($appointment) && $appointment && !empty($appointment->service_items_ids)) {
                            $chosenItemsIds = $appointment->service_items_ids;
                        }

                        $serviceItems = $service->items;
                        if ($chosenItemsIds !== null) {
                            $serviceItems = $serviceItems->filter(function ($product) use ($chosenItemsIds) {
                                return in_array($product->id, (array) $chosenItemsIds)
                                    || (isset($product->pivot->id) && in_array($product->pivot->id, (array) $chosenItemsIds));
                            })->values();
                        }

                        $itemsTotalPrice = 0;
                        foreach ($serviceItems as $product) {
                            $qtyPerService = (float) ($product->pivot->quantity ?? 1);
                            $requiredQty = $qtyPerService * $quantity;
                            $inventoryItem = Item::where('id', $product->id)->lockForUpdate()->first();

                            if (!$inventoryItem || $inventoryItem->current_stock < $requiredQty) {
                                $missingName = $inventoryItem ? $inventoryItem->name : $product->name;
                                throw new \Exception("الكمية المطلوبة من المادة المستهلكة ({$missingName}) غير متوفرة في المخزن لتنفيذ خدمة ({$itemName}).");
                            }

                            // سعر المستلزم الطبي المخصص لهذه الخدمة (من جدول الربط service_items) إن وجد، وإلا سعر بيع الصنف في المخزن مضروباً في كميته
                            $itemPrice = isset($product->pivot->price) && (float) $product->pivot->price > 0
                                ? (float) $product->pivot->price
                                : ((float) $inventoryItem->selling_price * $qtyPerService);

                            $itemsTotalPrice += $itemPrice;
                            $consumedIds[] = $product->id;

                            $serviceItemsToDeduct[] = [
                                'product_id' => $product->id,
                                'quantity' => $requiredQty
                            ];
                        }

                        // سعر الوحدة = سعر الخدمة الأساسي + مجموع أسعار المواد المستهلكة
                        $unitPrice = $serviceBasePrice + $itemsTotalPrice;
                    } elseif ($item['item_type'] === 'product') {
                        $inventoryItem = Item::where('id', $item['product_id'])->lockForUpdate()->first();
                        if (!$inventoryItem) {
                            throw new \Exception('المنتج المحدد غير موجود في المخزن.');
                        }

                        if ($inventoryItem->current_stock < $quantity) {
                            throw new \Exception("الكمية المطلوبة للصنف ({$inventoryItem->name}) غير متوفرة في المخزن حالياً.");
                        }

                        $itemName = $inventoryItem->name;
                        $unitPrice = (float) $inventoryItem->selling_price;
                    } elseif ($item['item_type'] === 'package') {
                        $package = Package::with('items')->find($item['package_id']);
                        if (!$package) {
                            throw new \Exception('الباقة المحددة غير موجودة.');
                        }
                        $itemName = 'باقة: ' . $package->name;
                        $unitPrice = (float) $package->price;
                    } elseif ($item['item_type'] === 'package_consumption') {
                        $balance = PatientPackageBalance::with(['patientPackage.package', 'service', 'product'])->find($item['patient_package_balance_id']);
                        if (!$balance) {
                            throw new \Exception('رصيد الباقة المحدد غير موجود.');
                        }
                        $requestedQty = (float) ($item['quantity'] ?? 1);
                        if ($requestedQty > (float) $balance->remaining_quantity) {
                            throw new \Exception("الرصيد المتبقي للبند ({$balance->remaining_quantity}) غير كافٍ لاستهلاك ({$requestedQty}).");
                        }
                        $servTitle = $balance->custom_name ?? ($balance->service ? $balance->service->name : ($balance->product ? $balance->product->name : 'باقة'));
                        $itemName = 'استهلاك باقة: ' . $servTitle;
                        $unitPrice = 0.00; // مجانية في فاتورة اليوم لأنها مسددة أو مقيدة على الباقة
                    } elseif ($item['item_type'] === 'package_debt_payment') {
                        $pkg = PatientPackage::with('package')->find($item['patient_package_id']);
                        if (!$pkg) {
                            throw new \Exception('الباقة المحددة غير موجودة.');
                        }
                        $payAmount = (float) ($item['amount'] ?? 0);
                        if ($payAmount <= 0) {
                            throw new \Exception('المبلغ المطلوب سداده يجب أن يكون أكبر من الصفر.');
                        }
                        $itemName = 'سداد قسط باقة: ' . $pkg->package->name;
                        $unitPrice = $payAmount;
                        $quantity = 1;
                    }

                    $totalPrice = $unitPrice * $quantity;
                    $subTotal += $totalPrice;

                    $processedItems[] = [
                        'item_type' => $item['item_type'],
                        'service_id' => $item['service_id'] ?? ($balance->service_id ?? null),
                        'product_id' => $item['product_id'] ?? ($balance->product_id ?? null),
                        'package_id' => $item['package_id'] ?? ($package->id ?? ($balance->patientPackage->package_id ?? ($pkg->package_id ?? null))),
                        'patient_package_id' => $item['patient_package_id'] ?? ($balance->patient_package_id ?? ($pkg->id ?? null)),
                        'patient_package_balance_id' => $item['patient_package_balance_id'] ?? ($balance->id ?? null),
                        'service_items_ids' => !empty($consumedIds) ? array_values(array_unique($consumedIds)) : null,
                        'item_name' => $itemName,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'total_price' => $totalPrice,
                        'service_consumed_items' => $serviceItemsToDeduct ?? [],
                        '_package_model' => $package ?? null,
                        '_balance_model' => $balance ?? null,
                        '_pkg_subscription' => $pkg ?? null,
                        '_debt_pay_amount' => $payAmount ?? null,
                        '_notes' => $item['notes'] ?? null,
                    ];
                }

                $discount = $validated['discount'] ?? 0;
                $patient = Patient::find($patientId);

                if ($patient && $patient->is_staff) {
                    $staffDiscountPercentage = Setting::where('key', 'staff_discount_percentage')->value('value') ?? 0;
                    if ($staffDiscountPercentage > 0) {
                        $discount = ($subTotal * $staffDiscountPercentage) / 100;
                    }
                }

                $grandTotal = max(0, $subTotal - $discount);

                // 1. تحديد المبلغ المدفوع والمتبقي وحالة الفاتورة
                $paidAmount = isset($validated['paid_amount']) ? (float) $validated['paid_amount'] : $grandTotal;
                $paidAmount = min($paidAmount, $grandTotal);
                $remainingAmount = max(0, $grandTotal - $paidAmount);

                if ($paidAmount >= $grandTotal) {
                    $status = InvoiceStatusEnum::PAID->value;
                } elseif ($paidAmount > 0) {
                    $status = InvoiceStatusEnum::PARTIALLY_PAID->value;
                } else {
                    $status = InvoiceStatusEnum::UNPAID->value;
                }

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'patient_id' => $patientId,
                    'appointment_id' => $appointmentId,
                    'doctor_id' => $doctorId,
                    'nurse_id' => $nurseId,
                    'shift_id' => $activeShift->id,
                    'queue_number' => $queueNumber,
                    'type' => $invoiceType,
                    'status' => $status,
                    'payment_method' => $validated['payment_method'],
                    'sub_total' => $subTotal,
                    'discount' => $discount,
                    'grand_total' => $grandTotal,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                foreach ($processedItems as $itemData) {
                    $consumedItems = $itemData['service_consumed_items'] ?? [];
                    $packageModel = $itemData['_package_model'] ?? null;
                    $balanceModel = $itemData['_balance_model'] ?? null;
                    $pkgSubscription = $itemData['_pkg_subscription'] ?? null;
                    $debtPayAmount = $itemData['_debt_pay_amount'] ?? null;
                    $consumptionNotes = $itemData['_notes'] ?? null;

                    unset(
                        $itemData['service_consumed_items'],
                        $itemData['_package_model'],
                        $itemData['_balance_model'],
                        $itemData['_pkg_subscription'],
                        $itemData['_debt_pay_amount'],
                        $itemData['_notes']
                    );

                    $invoiceItem = $invoice->items()->create($itemData);

                    if ($itemData['item_type'] === 'product' && !empty($itemData['product_id'])) {
                        $inventoryItem = Item::find($itemData['product_id']);
                        if ($inventoryItem) {
                            $inventoryItem->decrement('current_stock', $itemData['quantity']);
                        }
                    } elseif ($itemData['item_type'] === 'service' && !empty($consumedItems)) {
                        foreach ($consumedItems as $consumed) {
                            $inventoryItem = Item::find($consumed['product_id']);
                            if ($inventoryItem) {
                                $inventoryItem->decrement('current_stock', $consumed['quantity']);
                            }
                        }
                    } elseif ($itemData['item_type'] === 'package' && $packageModel) {
                        // 1. إنشاء اشتراك الباقة ومحفظة المريض
                        $pkgTotalPrice = (float) $invoiceItem->total_price;
                        $pkgPaid = min($invoice->paid_amount, $pkgTotalPrice);
                        $pkgRemaining = max(0, $pkgTotalPrice - $pkgPaid);

                        $patientPackage = PatientPackage::create([
                            'patient_id' => $patientId,
                            'package_id' => $packageModel->id,
                            'invoice_id' => $invoice->id,
                            'total_price' => $pkgTotalPrice,
                            'paid_amount' => $pkgPaid,
                            'remaining_amount' => $pkgRemaining,
                            'status' => PatientPackageStatusEnum::ACTIVE,
                            'start_date' => today(),
                            'expires_at' => $packageModel->validity_days ? today()->addDays($packageModel->validity_days) : null,
                            'notes' => $invoice->notes,
                            'created_by' => $userId,
                        ]);

                        $invoiceItem->update(['patient_package_id' => $patientPackage->id]);

                        // 2. تفريغ أرصدة بنود الباقة في المحفظة
                        foreach ($packageModel->items as $pItem) {
                            $balanceTotalQty = (float) ($pItem->quantity * $invoiceItem->quantity);
                            PatientPackageBalance::create([
                                'patient_package_id' => $patientPackage->id,
                                'item_type' => $pItem->item_type,
                                'service_id' => $pItem->service_id,
                                'product_id' => $pItem->product_id,
                                'custom_name' => $pItem->custom_name,
                                'total_quantity' => $balanceTotalQty,
                                'consumed_quantity' => 0,
                                'remaining_quantity' => $balanceTotalQty,
                                'unit' => $pItem->unit,
                            ]);
                        }
                    } elseif ($itemData['item_type'] === 'package_consumption' && $balanceModel) {
                        // 1. خصم الرصيد المستهلك من محفظة المريض
                        $consumeQty = (float) $invoiceItem->quantity;
                        $balanceModel->increment('consumed_quantity', $consumeQty);
                        $balanceModel->decrement('remaining_quantity', $consumeQty);

                        // 2. تسجيل حركة الاستهلاك لربطها بالطبيب والعمولة والحجز
                        PatientPackageConsumption::create([
                            'patient_package_id' => $balanceModel->patient_package_id,
                            'patient_package_balance_id' => $balanceModel->id,
                            'appointment_id' => $appointmentId,
                            'invoice_id' => $invoice->id,
                            'invoice_item_id' => $invoiceItem->id,
                            'doctor_id' => $doctorId ?? $userId,
                            'nurse_id' => $nurseId,
                            'consumed_quantity' => $consumeQty,
                            'unit' => $balanceModel->unit,
                            'notes' => $consumptionNotes,
                            'consumed_at' => now(),
                            'created_by' => $userId,
                        ]);

                        // 3. خصم من المخزن لو كانت مادة مستهلكة (فيلر/بوتوكس)
                        if ($balanceModel->product_id) {
                            $inventoryItem = Item::find($balanceModel->product_id);
                            if ($inventoryItem) {
                                $inventoryItem->decrement('current_stock', $consumeQty);
                            }
                        }

                        // 4. فحص اكتمال الباقة إذا نفدت جميع الأرصدة
                        $hasRemaining = PatientPackageBalance::where('patient_package_id', $balanceModel->patient_package_id)
                            ->where('remaining_quantity', '>', 0)
                            ->exists();

                        if (!$hasRemaining) {
                            $balanceModel->patientPackage()->update(['status' => PatientPackageStatusEnum::COMPLETED]);
                        }
                    } elseif ($itemData['item_type'] === 'package_debt_payment' && $pkgSubscription && $debtPayAmount > 0) {
                        // سداد جزء من مديونية الباقة
                        $pkgSubscription->increment('paid_amount', $debtPayAmount);
                        $pkgSubscription->decrement('remaining_amount', $debtPayAmount);
                    }
                }

                if ($paidAmount > 0) {
                    Transaction::create([
                        'transaction_number' => 'TRX-' . date('Y') . '-' . strtoupper(uniqid()),
                        'invoice_id' => $invoice->id,
                        'shift_id' => $activeShift->id,
                        'type' => TransactionTypeEnum::INCOME->value,
                        'payment_method' => $validated['payment_method'],
                        'amount' => $paidAmount,
                        'description' => 'تحصيل فاتورة مبيعات رقم ' . $invoice->invoice_number . ($status === InvoiceStatusEnum::PARTIALLY_PAID->value ? ' (دفعة جزئية)' : ''),
                        'created_by' => $userId,
                    ]);
                }

                return API::newInstance()->isCreated('تم إنشاء الفاتورة بنجاح.')
                    ->setData(new InvoiceResource($invoice->load(['items.service.items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))
                    ->build();
            });
        } catch (\Exception $e) {
            return API::newInstance()->isError($e->getMessage())->build();
        }
    }


    public function refund($id, $request)
    {
        $validated = $request->validated();
        $userId = Auth::id();

        $activeShift = Shift::where('user_id', $userId)->where('status', ShiftStatusEnum::OPEN->value)->first();
        if (!$activeShift) {
            return API::newInstance()->isError('يجب فتح شفت أولاً لإجراء عملية الاسترداد من الخزنة.')->build();
        }

        try {
            return DB::transaction(function () use ($id, $validated, $activeShift, $userId) {
                $invoice = Invoice::with('items')->find($id);
                if (!$invoice) {
                    throw new \Exception('الفاتورة المحددة غير موجودة.');
                }

                if ($invoice->status === InvoiceStatusEnum::REFUNDED->value) {
                    return API::newInstance()->isError('هذه الفاتورة مستردة بالكامل بالفعل!')->build();
                }

                $isFullRefund = $validated['is_full_refund'] ?? false;
                $refundAmountTotal = 0;

                if ($isFullRefund) {
                    $refundAmountTotal = max(0, (float) $invoice->paid_amount - (float) $invoice->refunded_amount);

                    foreach ($invoice->items as $invoiceItem) {
                        $qtyToReturn = $invoiceItem->quantity - $invoiceItem->returned_qty;
                        if ($qtyToReturn > 0) {

                            if ($invoiceItem->item_type === 'product' && !empty($invoiceItem->product_id)) {
                                $item = Item::find($invoiceItem->product_id);
                                if ($item) $item->increment('current_stock', $qtyToReturn);
                            } elseif ($invoiceItem->item_type === 'service' && !empty($invoiceItem->service_id)) {
                                $service = Service::with('items')->find($invoiceItem->service_id);
                                if ($service) {
                                    foreach ($service->items as $product) {
                                        $qtyToIncrement = $product->pivot->quantity * $qtyToReturn;
                                        $item = Item::find($product->id);
                                        if ($item) {
                                            $item->increment('current_stock', $qtyToIncrement);
                                        }
                                    }
                                }
                            }

                            $invoiceItem->update(['returned_qty' => $invoiceItem->quantity]);
                        }
                    }

                    $invoice->update([
                        'status' => InvoiceStatusEnum::REFUNDED->value,
                        'refunded_amount' => $invoice->paid_amount
                    ]);

                    if ($invoice->appointment_id) {
                        Appointment::where('id', $invoice->appointment_id)->update(['status' => AppointmentStatusEnum::CANCELLED->value]);
                    }
                } else {
                    foreach ($validated['items'] as $refundItem) {
                        $invoiceItem = $invoice->items()->whereKey($refundItem['invoice_item_id'])->first();
                        if (!$invoiceItem) continue;

                        $qtyToReturn = $refundItem['return_quantity'];
                        $availableToReturn = $invoiceItem->quantity - $invoiceItem->returned_qty;

                        if ($qtyToReturn > $availableToReturn) {
                            throw new \Exception("الكمية المرتجعة للصنف {$invoiceItem->item_name} أكبر من المسموح.");
                        }

                        $invoiceItem->increment('returned_qty', $qtyToReturn);
                        $itemRefundValue = $invoiceItem->unit_price * $qtyToReturn;
                        $refundAmountTotal += $itemRefundValue;

                        if ($invoiceItem->item_type === 'product' && !empty($invoiceItem->product_id)) {
                            $item = Item::find($invoiceItem->product_id);
                            if ($item) $item->increment('current_stock', $qtyToReturn);
                        } elseif ($invoiceItem->item_type === 'service' && !empty($invoiceItem->service_id)) {
                            $service = Service::with('items')->find($invoiceItem->service_id);
                            if ($service) {
                                foreach ($service->items as $product) {
                                    $qtyToIncrement = $product->pivot->quantity * $qtyToReturn;
                                    $item = Item::find($product->id);
                                    if ($item) {
                                        $item->increment('current_stock', $qtyToIncrement);
                                    }
                                }
                            }
                        }
                    }

                    $invoice->increment('refunded_amount', $refundAmountTotal);

                    if ($invoice->refunded_amount >= $invoice->grand_total) {
                        $invoice->update(['status' => InvoiceStatusEnum::REFUNDED->value, 'refunded_amount' => $invoice->grand_total]);
                        if ($invoice->appointment_id) {
                            Appointment::where('id', $invoice->appointment_id)->update(['status' => AppointmentStatusEnum::CANCELLED->value]);
                        }
                    }
                }

                if ($refundAmountTotal > 0) {
                    Transaction::create([
                        'transaction_number' => 'TRX-' . date('Y') . '-' . strtoupper(uniqid()),
                        'invoice_id' => $invoice->id,
                        'shift_id' => $activeShift->id,
                        'type' => TransactionTypeEnum::REFUND->value,
                        'payment_method' => $invoice->payment_method,
                        'amount' => $refundAmountTotal,
                        'description' => ($isFullRefund ? 'استرداد كلي' : 'استرداد جزئي') . ' لفاتورة ' . $invoice->invoice_number,
                        'created_by' => $userId,
                    ]);
                }

                return API::newInstance()->isOk('تمت عملية الاسترداد بنجاح.')->setData(new InvoiceResource($invoice->fresh(['items.service.items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))->build();
            });
        } catch (\Exception $e) {
            return API::newInstance()->isError($e->getMessage())->build();
        }
    }

    /**
     * سداد دفعة من المبلغ المتبقي على الفاتورة
     */
    public function payRemaining($id, $request)
    {
        $validated = $request->validated();
        $userId = Auth::id();

        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if (!$activeShift) {
            return API::newInstance()->isError('لا يمكنك سداد الفاتورة. يجب فتح شفت أولاً.')->build();
        }

        try {
            return DB::transaction(function () use ($id, $validated, $activeShift, $userId) {
                $invoice = Invoice::lockForUpdate()->findOrFail($id);

                if ($invoice->remaining_amount <= 0 || $invoice->status === InvoiceStatusEnum::PAID) {
                    return API::newInstance()->isError('هذه الفاتورة مدفوعة بالكامل بالفعل.')->build();
                }

                $payAmount = (float) $validated['amount'];
                if ($payAmount > (float) $invoice->remaining_amount) {
                    return API::newInstance()->isError("المبلغ المدخل ({$payAmount}) أكبر من المبلغ المتبقي على الفاتورة ({$invoice->remaining_amount}).")->build();
                }

                $newPaidAmount = (float) $invoice->paid_amount + $payAmount;
                $newRemainingAmount = max(0, (float) $invoice->remaining_amount - $payAmount);
                $newStatus = $newRemainingAmount <= 0 ? InvoiceStatusEnum::PAID->value : InvoiceStatusEnum::PARTIALLY_PAID->value;

                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'remaining_amount' => $newRemainingAmount,
                    'status' => $newStatus,
                ]);

                Transaction::create([
                    'transaction_number' => 'TRX-' . date('Y') . '-' . strtoupper(uniqid()),
                    'invoice_id' => $invoice->id,
                    'shift_id' => $activeShift->id,
                    'type' => TransactionTypeEnum::INCOME->value,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $payAmount,
                    'description' => 'سداد جزء من متبقي فاتورة رقم ' . $invoice->invoice_number,
                    'created_by' => $userId,
                ]);

                return API::newInstance()
                    ->isOk('تم تسجيل دفعة الفاتورة بنجاح.')
                    ->setData(new InvoiceResource($invoice->load(['items.service.items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))
                    ->build();
            });
        } catch (\Exception $e) {
            return API::newInstance()->isError($e->getMessage())->build();
        }
    }

    public function show($id)
    {
        $record = Invoice::with(['items.service.items', 'patient', 'doctor', 'nurse', 'shift', 'creator'])->find($id);
        if (!$record) return API::newInstance()->isError('Record not found')->build();
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new InvoiceResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Invoice::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new InvoiceResource($record->load(['items.service.items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))->build();
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = Invoice::with('items')->findOrFail($id);
            foreach ($record->items as $invoiceItem) {
                if ($invoiceItem->item_type === 'product' && !empty($invoiceItem->product_id)) {
                    $item = Item::find($invoiceItem->product_id);
                    if ($item) $item->increment('current_stock', $invoiceItem->quantity);
                }
            }
            $record->delete();
            return API::newInstance()->isOk('Deleted successfully')->build();
        });
    }
}
