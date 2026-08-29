<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');


            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('nurse_id')->nullable(); // 1. خليناه يقبل null
            $table->foreign('nurse_id')->references('id')->on('users')->onDelete('set null'); // 2. غيرنا cascade إلى set null للأمان
            
            $table->unsignedBigInteger('service_id')->nullable();


            $table->unsignedBigInteger('shift_id')->nullable(); // الشفت المرتبط به الحجز
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');

            $table->integer('queue_number')->default(1);


            $table->dateTime('appointment_date');


            $table->string('visit_type')->default('consultation');
            $table->string('status')->default('pending');


            $table->text('notes')->nullable();


            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
