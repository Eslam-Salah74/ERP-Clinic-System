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
use Modules\Reception\Enums\VisitTypeEnum;

class InvoiceService
{
    public function index($request, InvoiceFilter $filter)
    {
        $data = Invoice::filter($filter)
            ->with(['items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])
            ->latest()
            ->paginate(10);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(InvoiceResource::collection($data))->build();
    }

    /**
     * إنشاء فاتورة جديدة مع المعالجة الذكية للحجوزات والمخزن والخزنة
     */
    public function store($request)
    {
        $validated = $request->validated();
        $userId = Auth::id();

        // 0. التحقق من وجود شفت مفتوح ومفعل للمستخدم الحالي
        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if (!$activeShift) {
            return API::newInstance()->isError('لا يمكنك إنشاء فاتورة. يجب فتح شفت أولاً.')->build();
        }

        return DB::transaction(function () use ($validated, $activeShift, $userId) {

            $patientId = $validated['patient_id'];
            $invoiceType = $validated['type']; // consultation, session, follow_up, direct_sale
            $appointmentId = $validated['appointment_id'] ?? null;
            $nurseId = $validated['nurse_id'] ?? null;
            $doctorId = $validated['doctor_id'] ?? null;

            // --- 1. التجهيز والربط الذكي للـ Appointment بناءً على نوع الفاتورة ---
            if ($invoiceType === InvoiceTypeEnum::DIRECT_SALE->value) {
                // لو بيع مباشر (صيدلية)، لا يوجد حجز طبي نهائياً
                $appointmentId = null;
            } else {
                // لو الخدمة طبية (كشف، جلسة، متابعة)
                if (empty($appointmentId)) {
                    // البحث الذكي: هل المريض عنده حجز منتظر (pending) اليوم؟
                    $todayAppointment = Appointment::where('patient_id', $patientId)
                        ->where('status', AppointmentStatusEnum::PENDING->value)
                        ->whereDate('appointment_date', today()) // حجز اليوم فقط
                        ->first();

                    if ($todayAppointment) {
                        // السيناريو الأول: المريض لديه حجز مسبق اليوم، سنعتمد عليه أوتوماتيكياً
                        $appointmentId = $todayAppointment->id;

                        // تحديث بيانات الحجز القديم وربطه بالشفت الحالي والدكتور والتمريض
                        $todayAppointment->update([
                            'status' => AppointmentStatusEnum::COMPLETED->value,
                            'nurse_id' => $nurseId ?? $todayAppointment->nurse_id,
                            'doctor_id' => $doctorId ?? $todayAppointment->doctor_id,
                            'shift_id' => $activeShift->id, // تحديث الشفت للشفت الحالي الذي تم فيه الدفع
                        ]);

                        $doctorId = $todayAppointment->doctor_id;
                    } else {
                        // السيناريو الثاني: المريض ليس لديه حجز مسبق (كشف فوري Walk-in)، سننشئ له حجزاً الآن
                        $dummyAppointment = Appointment::create([
                            'patient_id' => $patientId,
                            'doctor_id' => $doctorId,
                            'nurse_id' => $nurseId,
                            'service_id' => $validated['items'][0]['service_id'] ?? null,
                            'shift_id' => $activeShift->id,
                            'appointment_date' => now(),
                            'visit_type' => $invoiceType,
                            'status' => AppointmentStatusEnum::COMPLETED->value,
                            'notes' => 'حجز فوري (Walk-in) تم إنشاؤه واكتشافه تلقائياً مع الفاتورة',
                            'created_by' => $userId,
                        ]);

                        $appointmentId = $dummyAppointment->id;
                    }
                } else {
                    // السيناريو الثالث: الموظف باعت appointment_id محدد صراحة في الـ Request
                    $appointment = Appointment::findOrFail($appointmentId);
                    $appointment->update([
                        'status' => AppointmentStatusEnum::COMPLETED->value,
                        'nurse_id' => $nurseId ?? $appointment->nurse_id,
                        'shift_id' => $activeShift->id, // تحديث الشفت للشفت الحالي
                    ]);
                    $doctorId = $appointment->doctor_id; // ضمان تطابق الدكتور
                }
            }

            // --- 2. توليد رقم فاتورة سيريال محمي ضد التزامن (Race Condition Safe) ---
            $year = date('Y');
            $lastInvoice = Invoice::whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->lockForUpdate() // قفل الجدول مؤقتاً لمنع تكرار السيريال
                ->first();

            $nextSequence = 1;
            if ($lastInvoice) {
                $parts = explode('-', $lastInvoice->invoice_number);
                $lastSeq = isset($parts[2]) ? intval($parts[2]) : 0;
                $nextSequence = $lastSeq + 1;
            }
            $invoiceNumber = 'INV-' . $year . '-' . str_pad($nextSequence, 6, '0', STR_PAD_LEFT);

            // --- 3. حساب رقم الدور (Queue Number) للدكتور ---
            $queueNumber = null;
            if (!empty($doctorId)) {
                $lastQueue = Invoice::where('doctor_id', $doctorId)
                    ->where('shift_id', $activeShift->id)
                    ->lockForUpdate()
                    ->max('queue_number');
                $queueNumber = $lastQueue ? $lastQueue + 1 : 1;
            }

            // --- 4. حساب الإجماليات وجلب الأسعار والأسماء مع قفل الأمان للمخزن ---
            $subTotal = 0; // <-- تعريف المتغير هنا بـ T كابيتال قبل الـ Loop
            $processedItems = [];
            foreach ($validated['items'] as $item) {
                $itemName = '';
                $unitPrice = 0;
                $serviceItemsToDeduct = []; // لتخزين المواد المرتبطة بالخدمة إن وجدت

                if ($item['item_type'] === 'service') {
                    $service = Service::with('items')->findOrFail($item['service_id']);
                    $itemName = $service->name;
                    $unitPrice = $service->price;

                    // التحقق المسبق: هل المواد المرتبطة بالجلسة دي متوفرة في المخزن؟
                    foreach ($service->items as $product) {
                        $requiredQty = $product->pivot->quantity * $item['quantity']; // الكمية مضروبة في عدد الجلسات
                        $inventoryItem = Item::where('id', $product->id)->lockForUpdate()->first();

                        if (!$inventoryItem || $inventoryItem->current_stock < $requiredQty) {
                            $missingName = $inventoryItem ? $inventoryItem->name : $product->name;
                            throw new \Exception("الكمية المطلوبة من المادة المستهلكة ({$missingName}) غير متوفرة في المخزن لتنفيذ خدمة ({$itemName}).");
                        }

                        // نحتفظ بالبيانات عشان هنخصمها قدام
                        $serviceItemsToDeduct[] = [
                            'product_id' => $product->id,
                            'quantity' => $requiredQty
                        ];
                    }
                } else {
                    // المنتجات المباشرة (Direct Sale / Pharmacy) زي ما هي بدون تغيير
                    $inventoryItem = Item::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                    if ($inventoryItem->current_stock < $item['quantity']) {
                        throw new \Exception("الكمية المطلوبة للصنف ({$inventoryItem->name}) غير متوفرة في المخزن حالياً.");
                    }

                    $itemName = $inventoryItem->name;
                    $unitPrice = $inventoryItem->selling_price;
                }

                $totalPrice = $unitPrice * $item['quantity'];
                $subTotal += $totalPrice;

                $processedItems[] = [
                    'item_type' => $item['item_type'],
                    'service_id' => $item['service_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $itemName,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'total_price' => $totalPrice,
                    'service_consumed_items' => $serviceItemsToDeduct ?? [], // بنشيلها مؤقتاً عشان نخصمها في خطوة الحفظ
                ];
            }

            // --- 5. التحقق من المريض هل هو موظف (is_staff) لتطبيق الخصم التلقائي ---
            $discount = $validated['discount'] ?? 0;
            $patient = Patient::find($patientId);

            if ($patient && $patient->is_staff) {
                $staffDiscountPercentage = Setting::where('key', 'staff_discount_percentage')->value('value') ?? 0;
                if ($staffDiscountPercentage > 0) {
                    $discount = ($subTotal * $staffDiscountPercentage) / 100;
                }
            }

            $grandTotal = max(0, $subTotal - $discount);

            // --- 6. إنشاء الفاتورة الأساسية ---
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'patient_id' => $patientId,
                'appointment_id' => $appointmentId,
                'doctor_id' => $doctorId,
                'nurse_id' => $nurseId,
                'shift_id' => $activeShift->id,
                'queue_number' => $queueNumber,
                'type' => $invoiceType,
                'status' => InvoiceStatusEnum::PAID->value,
                'payment_method' => $validated['payment_method'],
                'sub_total' => $subTotal,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // --- 7. حفظ تفاصيل العناصر وخصم المخزون ---
            // --- 7. حفظ تفاصيل العناصر وخصم المخزون ---
            foreach ($processedItems as $itemData) {
                // فصل بيانات الـ service_consumed_items عشان ما تتسجلش في جدول invoice_items لو مش موجودة في عمودها
                $consumedItems = $itemData['service_consumed_items'] ?? [];
                unset($itemData['service_consumed_items']);

                $invoiceItem = $invoice->items()->create($itemData);

                if ($itemData['item_type'] === 'product' && !empty($itemData['product_id'])) {
                    // خصم منتج التجزئة المباشر
                    $inventoryItem = Item::find($itemData['product_id']);
                    if ($inventoryItem) {
                        $inventoryItem->decrement('current_stock', $itemData['quantity']);
                    }
                } elseif ($itemData['item_type'] === 'service' && !empty($consumedItems)) {
                    // خصم المواد الخام المستهلكة المرتبطة بالجلسة (زي الميزو والفيلر من جدول service_items)
                    foreach ($consumedItems as $consumed) {
                        $inventoryItem = Item::find($consumed['product_id']);
                        if ($inventoryItem) {
                            $inventoryItem->decrement('current_stock', $consumed['quantity']);
                        }
                    }
                }
            }

            // --- 8. تسجيل الحركة المالية في الدرج (Transactions) ---
            if ($grandTotal > 0) {
                Transaction::create([
                    'transaction_number' => 'TRX-' . date('Y') . '-' . strtoupper(uniqid()),
                    'invoice_id' => $invoice->id,
                    'shift_id' => $activeShift->id,
                    'type' => TransactionTypeEnum::INCOME->value,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $grandTotal,
                    'description' => 'تحصيل فاتورة مبيعات رقم ' . $invoice->invoice_number,
                    'created_by' => $userId,
                ]);
            }

            // --- 9. إرجاع الـ Response النهائي مع كافة العلاقات ---
            return API::newInstance()->isCreated('تم إنشاء الفاتورة بنجاح.')
                ->setData(new InvoiceResource($invoice->load(['items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))
                ->build();
        });
    }


    public function refund($id, $request)
    {
        $validated = $request->validated();
        $userId = Auth::id();

        $activeShift = Shift::where('user_id', $userId)->where('status', ShiftStatusEnum::OPEN->value)->first();
        if (!$activeShift) {
            return API::newInstance()->isError('يجب فتح شفت أولاً لإجراء عملية الاسترداد من الخزنة.')->build();
        }

        return DB::transaction(function () use ($id, $validated, $activeShift, $userId) {
            $invoice = Invoice::with('items')->findOrFail($id);

            if ($invoice->status === InvoiceStatusEnum::REFUNDED->value) {
                return API::newInstance()->isError('هذه الفاتورة مستردة بالكامل بالفعل!')->build();
            }

            $isFullRefund = $validated['is_full_refund'] ?? false;
            $refundAmountTotal = 0;

            if ($isFullRefund) {
                $refundAmountTotal = $invoice->grand_total - $invoice->refunded_amount;

                foreach ($invoice->items as $invoiceItem) {
                    $qtyToReturn = $invoiceItem->quantity - $invoiceItem->returned_qty;
                    if ($qtyToReturn > 0) {

                        // --- 1. إرجاع منتجات التجزئة المباشرة للمخزن ---
                        if ($invoiceItem->item_type === 'product' && !empty($invoiceItem->product_id)) {
                            $item = Item::find($invoiceItem->product_id);
                            if ($item) $item->increment('current_stock', $qtyToReturn);
                        }

                        // --- 2. إرجاع المواد الخام المستهلكة المرتبطة بالخدمات/الجلسات للمخزن ---
                        elseif ($invoiceItem->item_type === 'service' && !empty($invoiceItem->service_id)) {
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
                    'refunded_amount' => $invoice->grand_total
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

                    // --- إرجاع المخزن في حالة الاسترداد الجزئي ---
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

            return API::newInstance()->isOk('تمت عملية الاسترداد بنجاح.')->setData(new InvoiceResource($invoice->fresh(['items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))->build();
        });
    }

    public function show($id)
    {
        $record = Invoice::with(['items', 'patient', 'doctor', 'nurse', 'shift', 'creator'])->find($id);
        if (!$record) return API::newInstance()->isError('Record not found')->build();
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new InvoiceResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Invoice::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new InvoiceResource($record->load(['items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))->build();
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
