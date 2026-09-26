<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('item_type'); // service, pulse, product (PackageItemTypeEnum)
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('items')->nullOnDelete();
            $table->decimal('quantity', 10, 2)->default(1.00); // 8 جلسات، 2000 نبضة، 0.5 مل
            $table->string('unit')->default('session');        // session, pulse, ml, unit
            $table->string('custom_name')->nullable();         // اسم مخصص في العرض إن وجد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
