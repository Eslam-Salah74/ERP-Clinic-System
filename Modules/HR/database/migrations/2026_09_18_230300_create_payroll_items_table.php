<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('type'); // basic_salary, hourly_pay, overtime, late_deduction, holiday_allowance, service_commission, device_commission, product_commission, department_commission, target_bonus, allowance, deduction
            $table->string('description'); // تفصيل البند (مثال: عمولة كشف عادي - فاتورة رقم INV-001)
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_addition')->default(true); // true إضافة، false خصم
            $table->unsignedBigInteger('reference_id')->nullable(); // ID الفاتورة أو الشفت إذا وجد
            $table->string('reference_type')->nullable(); // invoice, shift, etc.
            $table->json('metadata')->nullable(); // تفاصيل إضافية عن الحساب
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
