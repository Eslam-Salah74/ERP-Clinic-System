<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_package_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained('patient_packages')->cascadeOnDelete();
            $table->string('item_type'); // service, pulse, product
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('custom_name')->nullable();
            $table->decimal('total_quantity', 10, 2)->default(0.00);
            $table->decimal('consumed_quantity', 10, 2)->default(0.00);
            $table->decimal('remaining_quantity', 10, 2)->default(0.00);
            $table->string('unit')->default('session');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_package_balances');
    }
};
