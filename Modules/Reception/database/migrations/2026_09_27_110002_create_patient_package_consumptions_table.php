<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_package_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained('patient_packages')->cascadeOnDelete();
            $table->foreignId('patient_package_balance_id')->constrained('patient_package_balances')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('consumed_quantity', 10, 2)->default(1.00);
            $table->string('unit')->default('session');
            $table->text('notes')->nullable();
            $table->timestamp('consumed_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_package_consumptions');
    }
};
