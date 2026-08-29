<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الصنف (مثل: سرنجات 5 سم، أعشاب مورينجا)
            $table->decimal('selling_price', 10, 2)->default(0); // سعر البيع للمريض لو الصنف ده بيتباع
            $table->string('unit')->default('piece');
            $table->string('type')->default('consumable'); // نوع الصنف (مستهلكات طبية وحقن - منتجات صيدلية وتجزئة)
           $table->decimal('current_stock', 10, 2)->default(0); // الرصيد الحالي (بيبدأ بصفر وهيزيد مع فواتير الشراء)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
