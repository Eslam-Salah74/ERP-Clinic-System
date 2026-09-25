<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة الأعمدة إذا لم تكن موجودة بالفعل (لضمان الأمان في حال تم تنفيذ جزء من الميجريشن)
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'start_date')) {
                $table->date('start_date')->nullable()->after('contract_id')->index();
            }
            if (!Schema::hasColumn('payrolls', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date')->index();
            }
        });

        // 2. إنشاء فهرس على user_id لدعم الـ Foreign Key قبل حذف الـ Unique Constraint
        // في MySQL InnoDB، الـ Foreign Key يحتاج لفهرس يدعمه. وبما أن الفهرس الوحيد كان الـ unique،
        // يجب إنشاء فهرس عادي على user_id أولاً حتى لا يرفض MySQL الحذف بالخطأ 1553.
        try {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->index('user_id', 'payrolls_user_id_index');
            });
        } catch (\Throwable $e) {
            // في حال كان الفهرس موجوداً بالفعل
        }

        // 3. الآن يمكن إسقاط قيد الفرادة القديم بأمان تام
        try {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropUnique('payrolls_user_id_month_unique');
            });
        } catch (\Throwable $e) {
            // في حال كان قد تم حذفه مسبقاً
        }

        // 4. تعبئة البيانات القديمة في حال وجود سجلات سابقة
        if (Schema::hasColumn('payrolls', 'month') && Schema::hasColumn('payrolls', 'start_date')) {
            try {
                DB::statement("
                    UPDATE payrolls 
                    SET start_date = STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d'),
                        end_date = LAST_DAY(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d'))
                    WHERE start_date IS NULL AND month IS NOT NULL AND month != ''
                ");
            } catch (\Throwable $e) {
                // في حال وجود مشكلة في توافق دالة التاريخ على بيئات مختلفة
            }
        }
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unique(['user_id', 'month'], 'payrolls_user_id_month_unique');
            
            if (Schema::hasColumn('payrolls', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('payrolls', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });

        try {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropIndex('payrolls_user_id_index');
            });
        } catch (\Throwable $e) {
        }
    }
};
