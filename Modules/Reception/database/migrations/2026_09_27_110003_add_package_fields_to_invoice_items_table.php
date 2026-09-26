<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('product_id')->constrained('packages')->nullOnDelete();
            $table->foreignId('patient_package_id')->nullable()->after('package_id')->constrained('patient_packages')->nullOnDelete();
            $table->foreignId('patient_package_balance_id')->nullable()->after('patient_package_id')->constrained('patient_package_balances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_items_patient_package_balance_id_foreign']);
            $table->dropForeign(['invoice_items_patient_package_id_foreign']);
            $table->dropForeign(['invoice_items_package_id_foreign']);
            $table->dropColumn(['package_id', 'patient_package_id', 'patient_package_balance_id']);
        });
    }
};
