<?php

namespace Modules\Reception\Filters\Appointment;

use App\Filters\Filters;

class AppointmentFilter extends Filters
{
    protected $var_filters = [
        'search',
        'phone',
        'patient_name',
        'name',
        'patient_id',
        'patientId',
        'doctor_id',
        'doctorId',
        'service_id',
        'serviceId',
        'visit_type',
        'visitType',
        'status',
        'appointment_date',
        'appointmentDate',
        'date',
        'from_date',
        'fromDate',
        'to_date',
        'toDate',
        'date_from',
        'date_to',
        'shift_id',
        'shiftId',
    ];

    /**
     * بحث شامل برقم هاتف المريض، اسمه، معرفه، أو اسم الخدمة
     */
    public function search($search)
    {
        return $this->builder->where(function ($q) use ($search) {
            $q->whereHas('patient', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $query->orWhere('id', $search);
                }
            })->orWhereHas('service', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            });

            if (is_numeric($search)) {
                $q->orWhere('id', $search)
                  ->orWhere('queue_number', $search);
            }
        });
    }

    /**
     * فلترة برقم هاتف المريض
     */
    public function phone($phone)
    {
        return $this->builder->whereHas('patient', function ($query) use ($phone) {
            $query->where('phone', 'like', "%{$phone}%");
        });
    }

    /**
     * فلترة باسم المريض
     */
    public function patient_name($name)
    {
        return $this->builder->whereHas('patient', function ($query) use ($name) {
            $query->where('name', 'like', "%{$name}%");
        });
    }

    public function patientName($name)
    {
        return $this->patient_name($name);
    }

    public function name($name)
    {
        return $this->patient_name($name);
    }

    /**
     * فلترة بمعرف المريض
     */
    public function patient_id($id)
    {
        if (is_array($id)) {
            return $this->builder->whereIn('patient_id', $id);
        }
        return $this->builder->where('patient_id', $id);
    }

    public function patientId($id)
    {
        return $this->patient_id($id);
    }

    /**
     * فلترة بتاريخ الحجز (اليوم المحدد)
     */
    public function appointment_date($date)
    {
        return $this->builder->whereDate('appointment_date', $date);
    }

    public function appointmentDate($date)
    {
        return $this->appointment_date($date);
    }

    public function date($date)
    {
        return $this->appointment_date($date);
    }

    /**
     * فلترة من تاريخ معين
     */
    public function from_date($date)
    {
        return $this->builder->whereDate('appointment_date', '>=', $date);
    }

    public function fromDate($date)
    {
        return $this->from_date($date);
    }

    public function date_from($date)
    {
        return $this->from_date($date);
    }

    /**
     * فلترة إلى تاريخ معين
     */
    public function to_date($date)
    {
        return $this->builder->whereDate('appointment_date', '<=', $date);
    }

    public function toDate($date)
    {
        return $this->to_date($date);
    }

    public function date_to($date)
    {
        return $this->to_date($date);
    }

    /**
     * فلترة بالطبيب المعالج
     */
    public function doctor_id($id)
    {
        if (is_array($id)) {
            return $this->builder->whereIn('doctor_id', $id);
        }
        return $this->builder->where('doctor_id', $id);
    }

    public function doctorId($id)
    {
        return $this->doctor_id($id);
    }

    /**
     * فلترة بنوع الخدمة المحجوزة
     */
    public function service_id($id)
    {
        if (is_array($id)) {
            return $this->builder->whereIn('service_id', $id);
        }
        return $this->builder->where('service_id', $id);
    }

    public function serviceId($id)
    {
        return $this->service_id($id);
    }

    /**
     * فلترة بحالة الحجز (pending, confirmed, completed, cancelled, ...)
     */
    public function status($status)
    {
        if (is_array($status)) {
            return $this->builder->whereIn('status', $status);
        }
        return $this->builder->where('status', $status);
    }

    /**
     * فلترة بنوع الزيارة (consultation, follow_up)
     */
    public function visit_type($type)
    {
        return $this->builder->where('visit_type', $type);
    }

    public function visitType($type)
    {
        return $this->visit_type($type);
    }

    /**
     * فلترة برقم الشفت
     */
    public function shift_id($id)
    {
        return $this->builder->where('shift_id', $id);
    }

    public function shiftId($id)
    {
        return $this->shift_id($id);
    }
}
