<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Enums\CommissionTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_service_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('staff_contracts')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('commission_type')->default(CommissionTypeEnum::FIXED->value); // fixed أو percentage
            $table->decimal('commission_value', 10, 2)->default(0); // القيمة الثابتة أو النسبة المحددة للدكتور
            $table->timestamps();

            // منع تكرار نفس الخدمة لنفس العقد
            $table->unique(['contract_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_service_commissions');
    }
};
