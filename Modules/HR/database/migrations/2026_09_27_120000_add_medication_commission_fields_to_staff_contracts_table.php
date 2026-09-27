<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Enums\CommissionTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            $table->string('medication_commission_type')->default(CommissionTypeEnum::PERCENTAGE->value)->after('device_session_commission');
            $table->decimal('medication_commission_value', 10, 2)->default(0)->after('medication_commission_type');
        });
    }

    public function down(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            $table->dropColumn(['medication_commission_type', 'medication_commission_value']);
        });
    }
};
