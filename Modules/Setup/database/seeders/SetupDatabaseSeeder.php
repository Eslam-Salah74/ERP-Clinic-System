<?php

namespace Modules\Setup\Database\Seeders;

use Modules\Setup\Database\Seeders\Setting\SettingPermissionDatabaseSeeder;
use Modules\Setup\Database\Seeders\Setting\SettingDatabaseSeeder;

use Modules\Setup\Database\Seeders\Service\ServicePermissionDatabaseSeeder;
use Modules\Setup\Database\Seeders\Service\ServiceDatabaseSeeder;

use Modules\Setup\Database\Seeders\Department\DepartmentPermissionDatabaseSeeder;
use Modules\Setup\Database\Seeders\Department\DepartmentDatabaseSeeder;

use Illuminate\Database\Seeder;

class SetupDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $this->call(SettingPermissionDatabaseSeeder::class);
        $this->call(DepartmentPermissionDatabaseSeeder::class);
        $this->call(ServicePermissionDatabaseSeeder::class);

        $this->call(SettingDatabaseSeeder::class);

        $this->call(DepartmentDatabaseSeeder::class);

        $this->call(ServiceDatabaseSeeder::class);

        $this->call(ServiceItemDatabaseSeeder::class);
        // $this->call([]);
    }
}
