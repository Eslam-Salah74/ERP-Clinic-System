<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // sessions, pulses, units_volume, mixed (PackageTypeEnum)
            $table->decimal('original_price', 10, 2)->default(0.00); // السعر الأصلي بدون خصم
            $table->decimal('price', 10, 2)->default(0.00);          // سعر البيع في العرض
            $table->integer('validity_days')->nullable();            // فترة الصلاحية بالأيام (اختياري)
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
