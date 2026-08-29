<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // مفتاح الإعداد (مثال: clinic_name)
            $table->text('value')->nullable(); // قيمة الإعداد (مثال: عيادة د. فلان)
            $table->string('display_name')->nullable(); // اسم يظهر للمستخدم في الفرونت اند (مثال: اسم العيادة)
            $table->string('type')->default('text');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
