<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Enums\CommissionTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('commission_template_items');
        Schema::dropIfExists('commission_templates');

        Schema::create('commission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('commission_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_template_id')->constrained('commission_templates')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('doctor_service_price', 10, 2)->default(0);
            $table->boolean('is_laser')->default(false);
            $table->string('commission_type')->default(CommissionTypeEnum::PERCENTAGE->value);
            $table->decimal('commission_value', 10, 2)->default(0);
            $table->decimal('target_commission_value', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['commission_template_id', 'service_id'], 'comm_tmpl_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_template_items');
        Schema::dropIfExists('commission_templates');
    }
};
