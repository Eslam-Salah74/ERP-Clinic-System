<?php

namespace Modules\Reception\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Reception\Models\FollowUp;

class FollowUpReminderNotification extends Notification
{
    use Queueable;

    public FollowUp $followUp;
    public string $reminderType; // 'today' or 'upcoming'
    public int $daysBefore;

    public function __construct(FollowUp $followUp, string $reminderType = 'today', int $daysBefore = 0)
    {
        $this->followUp = $followUp;
        $this->reminderType = $reminderType;
        $this->daysBefore = $daysBefore;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $patientName = $this->followUp->patient?->name ?? 'غير محدد';
        $doctorName = $this->followUp->doctor?->name ?? 'غير محدد';
        $dateFormatted = $this->followUp->follow_up_date ? $this->followUp->follow_up_date->format('Y-m-d H:i') : null;

        $isToday = $this->reminderType === 'today';

        $title = $isToday
            ? 'تنبيه: متابعة اليوم'
            : ($this->daysBefore === 1
                ? 'تنبيه: متابعة غداً'
                : "تنبيه: متابعة قادمة خلال {$this->daysBefore} أيام");

        $message = $isToday
            ? "تنبيه: يوجد موعد متابعة مجدول اليوم للمريض ({$patientName}) مع الطبيب ({$doctorName}) في تمام الساعة {$this->followUp->follow_up_date?->format('H:i')}."
            : "تذكير: موعد متابعة قادم للمريض ({$patientName}) مع الطبيب ({$doctorName}) بتاريخ {$dateFormatted}.";

        return [
            'title'          => $title,
            'message'        => $message,
            'type'           => 'follow_up',
            'reminder_type'  => $this->reminderType,
            'days_before'    => $this->daysBefore,
            'follow_up_id'   => $this->followUp->id,
            'appointment_id' => $this->followUp->appointment_id,
            'patient_id'     => $this->followUp->patient_id,
            'patient_name'   => $patientName,
            'doctor_id'      => $this->followUp->doctor_id,
            'doctor_name'    => $doctorName,
            'follow_up_date' => $this->followUp->follow_up_date?->toIso8601String(),
            'status'         => $this->followUp->status instanceof \BackedEnum ? $this->followUp->status->value : (string) $this->followUp->status,
        ];
    }
}
