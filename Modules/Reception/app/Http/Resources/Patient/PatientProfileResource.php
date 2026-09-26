<?php

namespace Modules\Reception\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\FollowUpStatusEnum;
use Modules\Reception\Http\Resources\Appointment\AppointmentResource;
use Modules\Reception\Http\Resources\FollowUp\FollowUpResource;
use Modules\Reception\Http\Resources\Invoice\InvoiceResource;

class PatientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoices = $this->relationLoaded('invoices') ? $this->invoices : $this->invoices()->with(['doctor', 'items.service.department', 'items.product', 'transactions.creator', 'creator', 'shift'])->get();
        $appointments = $this->relationLoaded('appointments') ? $this->appointments : $this->appointments()->with(['doctor', 'service.items', 'creator', 'shift'])->latest('appointment_date')->get();
        $followUps = $this->relationLoaded('followUps') ? $this->followUps : $this->followUps()->with(['doctor', 'appointment', 'creator', 'shift'])->latest('follow_up_date')->get();

        // 1. الحسابات المالية الدقيقة (دفع كام وباقي كام والمسترد والخصومات)
        $totalBilled = (float) $invoices->sum('grand_total');
        $totalPaid = (float) $invoices->sum('paid_amount');
        $totalRemaining = (float) $invoices->sum('remaining_amount');
        $totalRefunded = (float) $invoices->sum('refunded_amount');
        $totalDiscount = (float) $invoices->sum('discount');

        $accountStatus = 'settled';
        $accountStatusArabic = 'خالص (لا توجد مديونية)';
        if ($totalRemaining > 0) {
            $accountStatus = 'has_debt';
            $accountStatusArabic = "عليه مديونية قدرها {$totalRemaining} ج.م";
        } elseif ($totalPaid > $totalBilled) {
            $accountStatus = 'credit';
            $accountStatusArabic = 'له رصيد دائن';
        }

        // تفصيل الدفع حسب وسيلة السداد من الفواتير والتحصيلات
        $paymentMethodsBreakdown = [
            'cash'      => 0.0,
            'visa'      => 0.0,
            'wallet'    => 0.0,
            'insurance' => 0.0,
        ];

        $allTransactions = collect();
        foreach ($invoices as $inv) {
            if ($inv->relationLoaded('transactions')) {
                foreach ($inv->transactions as $trx) {
                    $allTransactions->push($trx);
                    $method = $trx->payment_method instanceof \BackedEnum ? $trx->payment_method->value : (string) $trx->payment_method;
                    if (isset($paymentMethodsBreakdown[$method])) {
                        $paymentMethodsBreakdown[$method] += (float) $trx->amount;
                    }
                }
            }
        }

        // لو مفيش transactions منفصلة، نعتمد على الفواتير المدفوعة
        if ($allTransactions->isEmpty()) {
            foreach ($invoices as $inv) {
                $method = $inv->payment_method instanceof \BackedEnum ? $inv->payment_method->value : (string) $inv->payment_method;
                if (isset($paymentMethodsBreakdown[$method])) {
                    $paymentMethodsBreakdown[$method] += (float) $inv->paid_amount;
                }
            }
        }

        // 2. تجميع الخدمات التي اشتراها المريض (Purchased Services)
        $servicesMap = [];
        // 3. تجميع المنتجات التي اشتراها المريض (Purchased Products)
        $productsMap = [];

        foreach ($invoices as $inv) {
            if (!$inv->relationLoaded('items')) {
                continue;
            }

            foreach ($inv->items as $item) {
                if ($item->item_type === 'service') {
                    $serviceKey = $item->service_id ? 'id_' . $item->service_id : 'name_' . $item->item_name;
                    if (!isset($servicesMap[$serviceKey])) {
                        $servicesMap[$serviceKey] = [
                            'service_id'      => $item->service_id,
                            'service_name'    => $item->item_name,
                            'department_name' => $item->service?->department?->name ?? 'عام',
                            'times_availed'   => 0,
                            'total_spent'     => 0.0,
                            'last_price'      => (float) $item->unit_price,
                            'last_availed_at' => $inv->created_at?->format('Y-m-d H:i'),
                            'history'         => [],
                        ];
                    }

                    $netQty = max(0, (int) $item->quantity - (int) $item->returned_qty);
                    $servicesMap[$serviceKey]['times_availed'] += $netQty;
                    $servicesMap[$serviceKey]['total_spent'] += (float) ($netQty * $item->unit_price);
                    $servicesMap[$serviceKey]['history'][] = [
                        'invoice_id'     => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'doctor_name'    => $inv->doctor?->name,
                        'date'           => $inv->created_at?->format('Y-m-d H:i'),
                        'quantity'       => $netQty,
                        'unit_price'     => (float) $item->unit_price,
                        'total_price'    => (float) ($netQty * $item->unit_price),
                    ];
                } elseif ($item->item_type === 'product') {
                    $prodKey = $item->product_id ? 'id_' . $item->product_id : 'name_' . $item->item_name;
                    if (!isset($productsMap[$prodKey])) {
                        $productsMap[$prodKey] = [
                            'product_id'        => $item->product_id,
                            'product_name'      => $item->item_name,
                            'unit'              => $item->product?->unit instanceof \BackedEnum ? $item->product->unit->value : ($item->product?->unit ?? 'قطعة'),
                            'total_quantity'    => 0,
                            'total_spent'       => 0.0,
                            'last_price'        => (float) $item->unit_price,
                            'last_purchased_at' => $inv->created_at?->format('Y-m-d H:i'),
                            'history'           => [],
                        ];
                    }

                    $netQty = max(0, (int) $item->quantity - (int) $item->returned_qty);
                    $productsMap[$prodKey]['total_quantity'] += $netQty;
                    $productsMap[$prodKey]['total_spent'] += (float) ($netQty * $item->unit_price);
                    $productsMap[$prodKey]['history'][] = [
                        'invoice_id'     => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'date'           => $inv->created_at?->format('Y-m-d H:i'),
                        'quantity'       => $netQty,
                        'unit_price'     => (float) $item->unit_price,
                        'total_price'    => (float) ($netQty * $item->unit_price),
                    ];
                }
            }
        }

        // 4. إحصائيات المواعيد والحجوزات (Appointments Summary)
        $now = Carbon::now();
        $completedAppointments = $appointments->filter(fn($a) => ($a->status instanceof \BackedEnum ? $a->status->value : $a->status) === AppointmentStatusEnum::COMPLETED->value);
        $pendingAppointments = $appointments->filter(fn($a) => ($a->status instanceof \BackedEnum ? $a->status->value : $a->status) === AppointmentStatusEnum::PENDING->value);
        $cancelledAppointments = $appointments->filter(fn($a) => ($a->status instanceof \BackedEnum ? $a->status->value : $a->status) === AppointmentStatusEnum::CANCELLED->value);

        $nextUpcomingAppointment = $appointments->filter(function ($a) use ($now) {
            $isPending = ($a->status instanceof \BackedEnum ? $a->status->value : $a->status) === AppointmentStatusEnum::PENDING->value;
            return $isPending && $a->appointment_date && Carbon::parse($a->appointment_date)->greaterThanOrEqualTo($now);
        })->sortBy('appointment_date')->first();

        $lastCompletedAppointment = $completedAppointments->sortByDesc('appointment_date')->first();

        // 5. إحصائيات المتابعات (Follow-Ups Summary)
        $pendingFollowUps = $followUps->filter(fn($f) => ($f->status instanceof \BackedEnum ? $f->status->value : $f->status) === FollowUpStatusEnum::PENDING->value);
        $completedFollowUps = $followUps->filter(fn($f) => ($f->status instanceof \BackedEnum ? $f->status->value : $f->status) === FollowUpStatusEnum::COMPLETED->value);

        $nextUpcomingFollowUp = $pendingFollowUps->filter(function ($f) use ($now) {
            return $f->follow_up_date && Carbon::parse($f->follow_up_date)->greaterThanOrEqualTo($now->copy()->startOfDay());
        })->sortBy('follow_up_date')->first();

        return [
            // البيانات الشخصية والتعريفية
            'patient_info' => [
                'id'                   => $this->id,
                'name'                 => $this->name,
                'phone'                => $this->phone,
                'gender'               => $this->gender,
                'gender_arabic'        => $this->gender === 'male' ? 'ذكر' : ($this->gender === 'female' ? 'أنثى' : $this->gender),
                'age'                  => $this->age,
                'is_staff'             => (bool) $this->is_staff,
                'created_by'           => $this->created_by,
                'creator_name'         => $this->creator?->name,
                'registered_at'        => $this->created_at?->format('Y-m-d H:i'),
                'registered_days_ago'  => $this->created_at ? (int) $this->created_at->diffInDays(Carbon::now()) : 0,
            ],

            // كشف الحساب والملخص المالي الشامل
            'financial_summary' => [
                'account_status'          => $accountStatus,
                'account_status_arabic'   => $accountStatusArabic,
                'total_invoices_count'    => $invoices->count(),
                'total_billed_amount'     => $totalBilled,
                'total_paid_amount'       => $totalPaid,
                'total_remaining_amount'  => $totalRemaining,
                'total_refunded_amount'   => $totalRefunded,
                'total_discount_received' => $totalDiscount,
                'payment_methods_summary' => $paymentMethodsBreakdown,
            ],

            // الخدمات الطبية التي اشتراها المريض بالتفصيل
            'purchased_services' => array_values($servicesMap),

            // المنتجات والمستلزمات الطبية التي اشتراها المريض بالتفصيل
            'purchased_products' => array_values($productsMap),

            // ملخص الحجوزات والمواعيد
            'appointments_summary' => [
                'total_appointments'        => $appointments->count(),
                'completed_count'           => $completedAppointments->count(),
                'pending_count'             => $pendingAppointments->count(),
                'cancelled_count'           => $cancelledAppointments->count(),
                'next_upcoming_appointment' => $nextUpcomingAppointment ? [
                    'id'               => $nextUpcomingAppointment->id,
                    'appointment_date' => $nextUpcomingAppointment->appointment_date?->format('Y-m-d H:i'),
                    'doctor_name'      => $nextUpcomingAppointment->doctor?->name,
                    'service_name'     => $nextUpcomingAppointment->service?->name,
                    'visit_type'       => $nextUpcomingAppointment->visit_type instanceof \BackedEnum ? $nextUpcomingAppointment->visit_type->value : (string) $nextUpcomingAppointment->visit_type,
                ] : null,
                'last_completed_appointment' => $lastCompletedAppointment ? [
                    'id'               => $lastCompletedAppointment->id,
                    'appointment_date' => $lastCompletedAppointment->appointment_date?->format('Y-m-d H:i'),
                    'doctor_name'      => $lastCompletedAppointment->doctor?->name,
                    'service_name'     => $lastCompletedAppointment->service?->name,
                ] : null,
            ],

            // ملخص المتابعات
            'follow_ups_summary' => [
                'total_follow_ups'       => $followUps->count(),
                'pending_count'          => $pendingFollowUps->count(),
                'completed_count'        => $completedFollowUps->count(),
                'next_upcoming_follow_up' => $nextUpcomingFollowUp ? [
                    'id'             => $nextUpcomingFollowUp->id,
                    'follow_up_date' => $nextUpcomingFollowUp->follow_up_date?->format('Y-m-d H:i'),
                    'doctor_name'    => $nextUpcomingFollowUp->doctor?->name,
                    'notes'          => $nextUpcomingFollowUp->notes,
                ] : null,
            ],

            // السجلات الكاملة
            'appointments' => AppointmentResource::collection($appointments),
            'follow_ups'   => FollowUpResource::collection($followUps),
            'invoices'     => InvoiceResource::collection($invoices),
            'transactions' => $allTransactions->sortByDesc('created_at')->values(),
        ];
    }
}
