<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Enums\PayrollStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('staff_contracts')->nullOnDelete();
            $table->string('month', 7)->index(); // YYYY-MM
            
            // الراتب الأساسي (يؤخذ من جدول المستخدمين)
            $table->decimal('basic_salary', 10, 2)->default(0);

            // إحصائيات الشفتات وساعات العمل
            $table->unsignedInteger('shifts_count')->default(0);
            $table->decimal('total_working_hours', 8, 2)->default(0);
            $table->decimal('hourly_pay', 10, 2)->default(0);

            // الإضافي والتأخيرات والغياب
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_amount', 10, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->decimal('late_deduction_amount', 10, 2)->default(0);
            $table->unsignedInteger('holiday_days')->default(0);
            $table->decimal('holiday_allowance_amount', 10, 2)->default(0);

            // عمولات الخدمات للدكاترة
            $table->unsignedInteger('services_count')->default(0);
            $table->decimal('service_commissions_amount', 10, 2)->default(0);

            // عمولات التمريض (جلسات الأجهزة + مبيعات الأدوية)
            $table->unsignedInteger('device_sessions_count')->default(0);
            $table->decimal('device_commissions_amount', 10, 2)->default(0);
            $table->decimal('product_sales_total', 10, 2)->default(0);
            $table->decimal('product_commissions_amount', 10, 2)->default(0);

            // عمولات الاستقبال والإدارة من نسب الأقسام
            $table->decimal('department_commissions_amount', 10, 2)->default(0);

            // التارجت والبونص
            $table->boolean('target_achieved')->default(false);
            $table->decimal('target_bonus_amount', 10, 2)->default(0);

            // بدلات واستقطاعات إضافية
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);

            // الإجمالي والصافي
            $table->decimal('gross_salary', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2)->default(0);

            // الحالة والاعتماد
            $table->string('status')->default(PayrollStatusEnum::DRAFT->value);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // منع تكرار مسير الراتب لنفس الموظف في نفس الشهر
            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
