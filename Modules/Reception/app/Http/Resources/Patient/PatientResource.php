<?php

namespace Modules\Reception\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use Modules\Reception\Http\Resources\Appointment\AppointmentResource;
use Modules\Reception\Http\Resources\FollowUp\FollowUpResource;
use Modules\Reception\Http\Resources\Invoice\InvoiceResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasInvoices = $this->relationLoaded('invoices');
        $invoices = $hasInvoices ? $this->invoices : null;

        // تضمين الإحصائيات والملخص المالي عند طلب تفاصيل المريض أو عند تحميل الفواتير
        $includeFinancial = $hasInvoices || $request->is('*patients*');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'age' => $this->age,
            'is_staff' => (bool) $this->is_staff,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name, // اسم الموظف اللي سجل المريض

            // 1. الإحصائيات الإجمالية لتعاملات المريض على السيستم
            'statistics' => $this->when($includeFinancial, function () use ($hasInvoices, $invoices) {
                return [
                    'appointments_count' => $this->relationLoaded('appointments')
                        ? $this->appointments->count()
                        : (int) ($this->appointments_count ?? $this->appointments()->count()),
                    'invoices_count' => $hasInvoices
                        ? $invoices->count()
                        : (int) ($this->invoices_count ?? $this->invoices()->count()),
                    'follow_ups_count' => $this->relationLoaded('followUps')
                        ? $this->followUps->count()
                        : (int) ($this->follow_ups_count ?? $this->followUps()->count()),
                ];
            }),

            // 2. الملخص المالي لحساب المريض (دفع كام ومتبقي كام)
            'financial_summary' => $this->when($includeFinancial, function () use ($hasInvoices, $invoices) {
                $invoicesList = $hasInvoices ? $invoices : $this->invoices()->get();
                return [
                    'total_invoices_amount'  => (float) $invoicesList->sum('grand_total'),
                    'total_paid_amount'      => (float) $invoicesList->sum('paid_amount'),
                    'total_remaining_amount' => (float) $invoicesList->sum('remaining_amount'),
                    'total_refunded_amount'  => (float) $invoicesList->sum('refunded_amount'),
                ];
            }),

            // 3. سجل الحجوزات والخدمات الطبية المحجوزة
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),

            // 4. سجل الفواتير والمبالغ المسددة والمتبقية
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),

            // 5. سجل المتابعات
            'follow_ups' => FollowUpResource::collection($this->whenLoaded('followUps')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
