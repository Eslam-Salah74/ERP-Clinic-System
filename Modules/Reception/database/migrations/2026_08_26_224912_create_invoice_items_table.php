<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // نوع العنصر: 'service' (خدمة طبية كشف/جلسة) أو 'product' (منتج من المخزن)
            $table->string('item_type');

            // لو كان خدمة طبية
           $table->unsignedBigInteger('service_id')->nullable();
            // لو كان منتج (هنربطها بجدول المنتجات لما نبني موديول المخازن، حالياً نسيبها unsignedBigInteger)
            $table->unsignedBigInteger('product_id')->nullable();

            // اسم العنصر عشان لو الخدمة أو المنتج اتحذفوا من السيستم، يفضل الاسم موجود في الفاتورة القديمة
            $table->string('item_name');

            // التفاصيل المالية للعنصر
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0.00); // السعر * الكمية

            // ده السحر بتاع المرتجع الجزئي للمخزن
            $table->integer('returned_qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
