<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Enums\ExpenseCategoryEnum;
use Modules\Reception\Enums\PaymentMethodEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // بيان/اسم النفقة (مثال: فاتورة كهرباء، شراء مستلزمات بوفيه، إيجار...)
            $table->string('category')->default(ExpenseCategoryEnum::UTILITY->value); // utility, buffet, maintenance, rent, salaries, other
            $table->decimal('amount', 10, 2); // قيمة النفقة المادية
            $table->string('payment_method')->default(PaymentMethodEnum::CASH->value);
            $table->date('expense_date'); // تاريخ الصرف
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete(); // الوردية المخصوم منها المصروف (إن وجد)
            $table->text('notes')->nullable(); // ملاحظات إضافية
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // الموظف المسؤول عن إدخال النفقة
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
