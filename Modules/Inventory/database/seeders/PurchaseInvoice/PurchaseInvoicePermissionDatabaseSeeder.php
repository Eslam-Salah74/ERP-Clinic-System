<?php

namespace Modules\Inventory\Database\Seeders\PurchaseInvoice;

use Illuminate\Database\Seeder;

class PurchaseInvoicePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'purchase_invoices' => 'Inventory',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}