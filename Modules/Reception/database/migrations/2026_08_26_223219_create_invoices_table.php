<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // رقم الفاتورة المميز (مثال: INV-2026-0001)
            $table->string('invoice_number')->unique();

            // الأطراف المرتبطة بالفاتورة
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();

            // لو الفاتورة كانت لحجز مسبق (هنا بنربطها عشان نغير حالته لـ Completed)
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            // الأطباء والممرضين (لحساب العمولات ولتوليد رقم الدور)
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained('users')->nullOnDelete();

            // الشفت اللي اتكريتت فيه الفاتورة دي أول مرة
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            // رقم الدور الذكي اللي بيتولد وقت الفاتورة للدكتور ده
            $table->integer('queue_number')->nullable();

            // نوع وحالة الفاتورة
            $table->string('type'); // consultation, session, direct_sale
            $table->string('status'); // paid, unpaid, refunded, cancelled
            $table->string('payment_method'); // cash, visa, wallet, insurance

            // الأرقام المالية
            $table->decimal('sub_total', 10, 2)->default(0.00); // الإجمالي قبل الخصم
            $table->decimal('discount', 10, 2)->default(0.00);  // قيمة الخصم (سواء للموظف أو خصم يدوي)
            $table->decimal('grand_total', 10, 2)->default(0.00); // الصافي المطلوب دفعه

            // ده الحقل السحري اللي بيسجل أي فلوس رجعت للمريض عشان المرتجع الجزئي
            $table->decimal('refunded_amount', 10, 2)->default(0.00);

            $table->text('notes')->nullable();

            // الكاشير اللي عمل الفاتورة دي أول مرة
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
