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

                if ($invoiceType === InvoiceTypeEnum::DIRECT_SALE->value) {
                    $appointmentId = null;
                } else {
                    if (empty($appointmentId)) {
                        $todayAppointment = Appointment::where('patient_id', $patientId)
                            ->where('status', AppointmentStatusEnum::PENDING->value)
                            ->whereDate('appointment_date', today())
                            ->first();

                        if ($todayAppointment) {
                            $appointmentId = $todayAppointment->id;

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
                    $quantity = isset($item['quantity']) && !empty($item['quantity']) ? (int)$item['quantity'] : 1;

                    if ($item['item_type'] === 'service') {
                        $service = Service::with('items')->find($item['service_id']);
                        if (!$service) {
                            throw new \Exception('الخدمة المحددة غير موجودة في قاعدة البيانات.');
                        }
                        $itemName = $service->name;
                        $unitPrice = $service->price;

                        foreach ($service->items as $product) {
                            $requiredQty = $product->pivot->quantity * $quantity;
                            $inventoryItem = Item::where('id', $product->id)->lockForUpdate()->first();

                            if (!$inventoryItem || $inventoryItem->current_stock < $requiredQty) {
                                $missingName = $inventoryItem ? $inventoryItem->name : $product->name;
                                throw new \Exception("الكمية المطلوبة من المادة المستهلكة ({$missingName}) غير متوفرة في المخزن لتنفيذ خدمة ({$itemName}).");
                            }

                            $serviceItemsToDeduct[] = [
                                'product_id' => $product->id,
                                'quantity' => $requiredQty
                            ];
                        }
                    } else {
                        $inventoryItem = Item::where('id', $item['product_id'])->lockForUpdate()->first();
                        if (!$inventoryItem) {
                            throw new \Exception('المنتج المحدد غير موجود في المخزن.');
                        }

                        if ($inventoryItem->current_stock < $quantity) {
                            throw new \Exception("الكمية المطلوبة للصنف ({$inventoryItem->name}) غير متوفرة في المخزن حالياً.");
                        }

                        $itemName = $inventoryItem->name;
                        $unitPrice = $inventoryItem->selling_price;
                    }

                    $totalPrice = $unitPrice * $quantity;
                    $subTotal += $totalPrice;

                    $processedItems[] = [
                        'item_type' => $item['item_type'],
                        'service_id' => $item['service_id'] ?? null,
                        'product_id' => $item['product_id'] ?? null,
                        'item_name' => $itemName,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'total_price' => $totalPrice,
                        'service_consumed_items' => $serviceItemsToDeduct ?? [],
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

                foreach ($processedItems as $itemData) {
                    $consumedItems = $itemData['service_consumed_items'] ?? [];
                    unset($itemData['service_consumed_items']);

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
                    }
                }

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

                return API::newInstance()->isCreated('تم إنشاء الفاتورة بنجاح.')
                    ->setData(new InvoiceResource($invoice->load(['items', 'patient', 'doctor', 'nurse', 'creator', 'shift'])))
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
                    $refundAmountTotal = $invoice->grand_total - $invoice->refunded_amount;

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
        } catch (\Exception $e) {
            return API::newInstance()->isError($e->getMessage())->build();
        }
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
