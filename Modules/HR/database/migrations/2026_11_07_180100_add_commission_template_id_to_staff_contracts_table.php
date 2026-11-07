<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            $table->foreignId('commission_template_id')
                ->nullable()
                ->after('notes')
                ->constrained('commission_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_template_id');
        });
    }
};

