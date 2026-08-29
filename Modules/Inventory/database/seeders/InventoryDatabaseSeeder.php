<?php

namespace Modules\Inventory\Database\Seeders;

use Modules\Inventory\Database\Seeders\PurchaseInvoice\PurchaseInvoicePermissionDatabaseSeeder;
use Modules\Inventory\Database\Seeders\PurchaseInvoice\PurchaseInvoiceDatabaseSeeder;

use Modules\Inventory\Database\Seeders\Item\ItemPermissionDatabaseSeeder;
use Modules\Inventory\Database\Seeders\Item\ItemDatabaseSeeder;

use Modules\Inventory\Database\Seeders\Supplier\SupplierPermissionDatabaseSeeder;
use Modules\Inventory\Database\Seeders\Supplier\SupplierDatabaseSeeder;

use Illuminate\Database\Seeder;

class InventoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $this->call(SupplierPermissionDatabaseSeeder::class);
        $this->call(ItemPermissionDatabaseSeeder::class);
        $this->call(PurchaseInvoicePermissionDatabaseSeeder::class);

        // 2. البيانات الأساسية المستقلة (الموردين والأصناف)
        $this->call(SupplierDatabaseSeeder::class);
        $this->call(ItemDatabaseSeeder::class);

        // 3. البيانات المرتبطة التي تعتمد على ما سبق (فواتير المشتريات تحتاج لموردين وأصناف)
        $this->call(PurchaseInvoiceDatabaseSeeder::class);
        // $this->call([]);
    }
}
