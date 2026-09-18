<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Enums\ContractTypeEnum;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\HR\Enums\TargetTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable(); // مسمى العقد (مثال: عقد أخصائي جلدية وليزر)
            $table->string('contract_type')->default(ContractTypeEnum::DOCTOR->value);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);

            // ساعات العمل والبدلات ومعدلات الأجر
            $table->decimal('hourly_rate', 10, 2)->default(0); // أجر الساعة (مثال: 40 أو 100)
            $table->decimal('working_hours_per_day', 4, 2)->default(8); // ساعات العمل الرسمية باليوم
            $table->decimal('overtime_hour_rate', 10, 2)->default(0); // أجر ساعة الإضافي
            $table->decimal('late_deduction_rate_per_hour', 10, 2)->default(0); // خصم ساعة التأخير
            $table->decimal('holiday_day_rate', 10, 2)->default(0); // بدل يوم الإجازة (مثل 50 ج للميسات)

            // عمولات الاستقبال والإدارة من إيرادات الأقسام
            $table->boolean('applies_department_commission')->default(false);
            $table->json('department_commissions')->nullable(); // مصفوفة بنسب الأقسام [{department_id: 1, percentage: 0.05}]

            // عمولات التمريض (جلسات الأجهزة + مبيعات الأدوية والمستلزمات)
            $table->decimal('device_session_commission', 10, 2)->default(0); // عمولة ثابتة لكل جلسة جهاز (مثل 5 ج)
            $table->decimal('medication_sales_percentage', 5, 2)->default(0); // نسبة من مبيعات الأدوية (مثل 2.5%)

            // عمولات الخدمات العامة ونسب الليزر والخدمات الأخرى (للدكاترة)
            $table->string('default_service_commission_type')->default(CommissionTypeEnum::PERCENTAGE->value);
            $table->decimal('default_service_commission_value', 10, 2)->default(0); // نسبة عامة للخدمات (مثل 20% أو 3% لتغذية)
            $table->decimal('laser_service_commission_percentage', 5, 2)->default(0); // نسبة خدمات الليزر (مثل 10% أو 15%)
            $table->decimal('other_service_commission_percentage', 5, 2)->default(0); // نسبة الخدمات الأخرى (مثل 15% أو 20%)

            // نظام التارجت والترقية الآلية للشريحة الأعلى (Generic Tiered Target)
            $table->boolean('has_target')->default(false);
            $table->decimal('target_amount', 10, 2)->default(0); // قيمة التارجت (مثل 7500 ج)
            $table->string('target_type')->default(TargetTypeEnum::DOCTOR_INCOME->value);
            $table->decimal('target_achieved_hourly_rate', 10, 2)->default(0); // سعر الساعة بعد تحقيق التارجت (مثل 100 ج)
            $table->decimal('target_achieved_laser_percentage', 5, 2)->default(0); // نسبة الليزر بعد تحقيق التارجت (مثل 15%)
            $table->decimal('target_achieved_other_percentage', 5, 2)->default(0); // نسبة الخدمات بعد تحقيق التارجت (مثل 20%)
            $table->decimal('target_bonus', 10, 2)->default(0); // مكافأة إضافية عند كسر التارجت

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contracts');
    }
};
