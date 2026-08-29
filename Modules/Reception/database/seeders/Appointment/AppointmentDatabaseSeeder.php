<?php

namespace Modules\Reception\Database\Seeders\Appointment;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\VisitTypeEnum;
use Modules\Reception\Models\Appointment;
use Modules\Reception\Models\Patient;
use Modules\Setup\Models\Service;

class AppointmentDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $patient = Patient::first();
        $doctor = User::where('type', 'doctor')->first();
        $service = Service::first();
        $creator = User::first();

        if ($patient && $doctor && $service) {
            Appointment::firstOrCreate(
                [
                    'patient_id' => $patient->id,
                    'appointment_date' => Carbon::now()->addDays(1), // موعد غداً
                ],
                [
                    'doctor_id' => $doctor->id,
                    'nurse_id' => null,
                    'service_id' => $service->id,
                    'visit_type' => VisitTypeEnum::CONSULTATION->value,
                    'status' => AppointmentStatusEnum::PENDING->value,
                    'notes' => 'حجز مبدئي تجريبي للكشف',
                    'created_by' => $creator?->id,
                ]
            );
        }
    }
}
