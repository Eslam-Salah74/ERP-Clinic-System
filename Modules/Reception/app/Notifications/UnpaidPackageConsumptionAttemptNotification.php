<?php

namespace Modules\Reception\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Reception\Models\PatientPackage;
use Modules\Reception\Models\PatientPackageBalance;

class UnpaidPackageConsumptionAttemptNotification extends Notification
{
    use Queueable;

    public PatientPackage $patientPackage;
    public PatientPackageBalance $balance;
    public ?User $employee;
    public float $requestedQty;
    public float $maxAllowedPct;
    public float $effectivePct;

    public function __construct(
        PatientPackage $patientPackage,
        PatientPackageBalance $balance,
        ?User $employee,
        float $requestedQty,
        float $maxAllowedPct,
        float $effectivePct
    ) {
        $this->patientPackage = $patientPackage;
        $this->balance = $balance;
        $this->employee = $employee;
        $this->requestedQty = $requestedQty;
        $this->maxAllowedPct = $maxAllowedPct;
        $this->effectivePct = $effectivePct;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $employeeName = $this->employee?->name ?? 'موظف غير محدد';
        $patient = $this->patientPackage->patient;
        $patientName = $patient?->name ?? 'غير محدد';
        $package = $this->patientPackage->package;
        $packageName = $package?->name ?? 'باقة غير محددة';
        $balanceItemName = $this->balance->custom_name ?? ($this->balance->service ? $this->balance->service->name : ($this->balance->product ? $this->balance->product->name : 'بند باقة'));
        $remainingAmount = (float) $this->patientPackage->remaining_amount;
        $remainingFormatted = number_format($remainingAmount, 2);

        $title = 'تنبيه: محاولة استهلاك من باقة مقيدة بمديونية';
        $message = "حاول الموظف ({$employeeName}) استهلاك رصيد ({$this->requestedQty}) من باقة ({$packageName}) للمريض ({$patientName}) بالرغم من تجاوز الحد المسموح به للاستهلاك ({$this->maxAllowedPct}%) في ظل وجود متبقٍ غير مسدد قدره ({$remainingFormatted} ج).";

        return [
            'title'                  => $title,
            'message'                => $message,
            'type'                   => 'package_debt_limit',
            'employee_id'            => $this->employee?->id,
            'employee_name'          => $employeeName,
            'patient_id'             => $patient?->id,
            'patient_name'           => $patientName,
            'patient_package_id'     => $this->patientPackage->id,
            'package_name'           => $packageName,
            'balance_id'             => $this->balance->id,
            'balance_item_name'      => $balanceItemName,
            'requested_quantity'     => $this->requestedQty,
            'remaining_amount'       => $remainingAmount,
            'max_allowed_percentage' => $this->maxAllowedPct,
            'effective_percentage'   => round($this->effectivePct, 2),
        ];
    }
}

