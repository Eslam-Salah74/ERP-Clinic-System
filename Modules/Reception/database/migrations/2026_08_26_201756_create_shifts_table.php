<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reception\Enums\ShiftStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // حالة الشفت (مفتوح / مغلق)
            $table->string('status')->default(ShiftStatusEnum::OPEN->value);

            // الحسابات المالية للخزنة
            $table->decimal('initial_balance', 10, 2)->default(0); // عهدة بداية الشفت
            $table->decimal('final_balance', 10, 2)->nullable();   // إجمالي الفلوس عند غلق الشفت

            // التوقيتات
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();

            // إحداثيات الـ GPS (للتحقق من موقع فتح الشفت في حالة تفعيل نظام البصمة الجغرافية)
            $table->decimal('opening_latitude', 10, 8)->nullable();
            $table->decimal('opening_longitude', 11, 8)->nullable();

            // إدارة الحضور، التأخير، والوقت الإضافي (Overtime)
            $table->boolean('is_late')->default(false);
            $table->integer('late_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->boolean('overtime_approved')->default(false); // لا يُحتسب الأوفرتايم إلا باعتماد الإدارة
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
