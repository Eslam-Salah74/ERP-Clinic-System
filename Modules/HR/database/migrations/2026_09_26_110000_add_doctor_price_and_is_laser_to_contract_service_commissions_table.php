<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_service_commissions', function (Blueprint $table) {
            $table->decimal('doctor_service_price', 10, 2)->default(0)->after('service_id');
            $table->boolean('is_laser')->default(false)->after('doctor_service_price');
            $table->decimal('target_commission_value', 10, 2)->nullable()->after('commission_value');
        });
    }

    public function down(): void
    {
        Schema::table('contract_service_commissions', function (Blueprint $table) {
            $table->dropColumn(['doctor_service_price', 'is_laser', 'target_commission_value']);
        });
    }
};
