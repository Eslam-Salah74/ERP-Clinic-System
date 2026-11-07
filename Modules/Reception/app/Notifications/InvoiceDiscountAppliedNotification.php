<?php

namespace Modules\Reception\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Reception\Models\Invoice;

class InvoiceDiscountAppliedNotification extends Notification
{
    use Queueable;

    public Invoice $invoice;
    public ?User $employee;
    public float $discountAmount;

    public function __construct(Invoice $invoice, ?User $employee, float $discountAmount)
    {
        $this->invoice = $invoice;
        $this->employee = $employee;
        $this->discountAmount = $discountAmount;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $employeeName = $this->employee?->name ?? 'موظف غير محدد';
        $patient = $this->invoice->patient;
        $patientName = $patient?->name ?? 'غير محدد';
        $discountFormatted = number_format($this->discountAmount, 2);
        $invoiceNumber = $this->invoice->invoice_number ?? ('#' . $this->invoice->id);

        $title = 'تنبيه: تطبيق خصم على فاتورة';
        $message = "قام الموظف ({$employeeName}) بعمل خصم على فاتورة للمريض ({$patientName}) بقيمة ({$discountFormatted} ج) - فاتورة رقم ({$invoiceNumber}).";

        return [
            'title'           => $title,
            'message'         => $message,
            'type'            => 'invoice_discount',
            'employee_id'     => $this->employee?->id,
            'employee_name'   => $employeeName,
            'patient_id'      => $patient?->id,
            'patient_name'    => $patientName,
            'invoice_id'      => $this->invoice->id,
            'invoice_number'  => $invoiceNumber,
            'discount_amount' => $this->discountAmount,
            'sub_total'       => (float) $this->invoice->sub_total,
            'grand_total'     => (float) $this->invoice->grand_total,
        ];
    }
}

