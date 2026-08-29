<?php

namespace Modules\Reception\Database\Seeders;

use Modules\Reception\Database\Seeders\Invoice\InvoicePermissionDatabaseSeeder;
use Modules\Reception\Database\Seeders\Invoice\InvoiceDatabaseSeeder;

use Modules\Reception\Database\Seeders\Shift\ShiftPermissionDatabaseSeeder;
use Modules\Reception\Database\Seeders\Shift\ShiftDatabaseSeeder;

use Modules\Reception\Database\Seeders\Appointment\AppointmentPermissionDatabaseSeeder;
use Modules\Reception\Database\Seeders\Appointment\AppointmentDatabaseSeeder;

use Modules\Reception\Database\Seeders\Patient\PatientPermissionDatabaseSeeder;
use Modules\Reception\Database\Seeders\Patient\PatientDatabaseSeeder;

use Illuminate\Database\Seeder;

class ReceptionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(PatientPermissionDatabaseSeeder::class);
        $this->call(AppointmentPermissionDatabaseSeeder::class);
        $this->call(ShiftPermissionDatabaseSeeder::class);
        $this->call(InvoicePermissionDatabaseSeeder::class);

        $this->call(PatientDatabaseSeeder::class);

        $this->call(ShiftDatabaseSeeder::class);

        $this->call(AppointmentDatabaseSeeder::class);

        $this->call(InvoiceDatabaseSeeder::class);
    }
}
