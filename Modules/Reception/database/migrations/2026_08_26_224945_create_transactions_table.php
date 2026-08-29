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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // رقم الحركة (مثال: TRX-2026-0001)
            $table->string('transaction_number')->unique();

            // ارتباط الحركة بالفاتورة (لو دي فلوس جاية من فاتورة أو مرتجع)
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // **الأهم:** الشفت اللي الفلوس دي دخلت أو خرجت منه فعلياً!
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();

            // نوع الحركة: income (دخول فلوس), expense (مصروفات), refund (استرداد/مرتجع)
            $table->string('type');

            // كاش ولا فيزا (عشان تقفيل الشفت يقولك معاك كاش كذا وفيزا كذا)
            $table->string('payment_method');

            // قيمة الفلوس (دايماً رقم موجب، والـ type هو اللي بيحدد هتتجمع ولا تتطرح)
            $table->decimal('amount', 10, 2)->default(0.00);

            // وصف الحركة (مثال: تحصيل فاتورة رقم كذا / استرداد فاتورة رقم كذا)
            $table->string('description')->nullable();

            // الموظف اللي الدرج كان في إيده وقت ما الفلوس دي اتحركت
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
