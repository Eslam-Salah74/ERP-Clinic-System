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
            $table->string('name');
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->string('unit')->default('piece');
            $table->string('stock_unit')->default('piece');
             $table->decimal('conversion_factor', 10, 3)->default(1);
            $table->string('type')->default('consumable');
            $table->decimal('current_stock', 10, 2)->default(0);
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
