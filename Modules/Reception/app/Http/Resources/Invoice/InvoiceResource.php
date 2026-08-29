<?php

namespace Modules\Reception\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'queue_number' => $this->queue_number ?? '---',

            // الأنواع والحالات
            'type' => $this->type,
            'status' => $this->status,
            'payment_method' => $this->payment_method,

            // التفاصيل المالية (محولة لـ float لضمان دقة الحسابات في الشاشة والطباعة)
            'sub_total' => (float) $this->sub_total,
            'discount' => (float) $this->discount,
            'grand_total' => (float) $this->grand_total,
            'refunded_amount' => (float) $this->refunded_amount,

            'notes' => $this->notes,

            // بيانات المريض (ضرورية جداً في إيصال الطباعة)
            'patient' => $this->relationLoaded('patient') && $this->patient ? [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
                'phone' => $this->patient->phone ?? '---',
            ] : null,

            // بيانات الدكتور المعالج
            'doctor' => $this->relationLoaded('doctor') && $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null,

            // بيانات التمريض (إن وجد)
            'nurse' => $this->relationLoaded('nurse') && $this->nurse ? [
                'id' => $this->nurse->id,
                'name' => $this->nurse->name,
            ] : null,

            // بيانات الشفت
            'shift' => $this->relationLoaded('shift') && $this->shift ? [
                'id' => $this->shift->id,
                'status' => $this->shift->status,
            ] : null,

            // موظف الاستقبال / الكاشير (اللي طبع الفاتورة)
            'creator' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,

            // تفاصيل العناصر (الخدمات والأدوية) - دي روح الإيصال
            'items' => $this->relationLoaded('items') ? $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'item_name' => $item->item_name, // الاسم الذي تم حفظه وقت البيع (اللقطة)
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                    'total_price' => (float) $item->total_price,
                    'returned_qty' => (int) $item->returned_qty,
                ];
            }) : [],

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
