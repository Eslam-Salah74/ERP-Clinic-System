<?php

namespace Modules\Reception\Database\Seeders\Invoice;

use Illuminate\Database\Seeder;

class InvoicePermissionDatabaseSeeder extends Seeder
{
    use \App\Traits\PermissionSeederTrait;

    public function run(): void
    {
        $actions = ['read', 'create', 'show', 'update', 'delete'];
        $models = [
            'invoices' => 'Reception',
        ];

        $this->createOrUpdatePermissions($models, $actions);
    }
}