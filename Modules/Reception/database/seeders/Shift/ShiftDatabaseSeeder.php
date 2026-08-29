<?php

namespace Modules\Reception\Database\Seeders\Shift;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Models\Shift;

class ShiftDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if ($user) {
            Shift::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => ShiftStatusEnum::CLOSED->value,
                ],
                [
                    'initial_balance' => 1000.00, // عهدة البداية الافتراضية
                    'final_balance' => 2500.00,   // إجمالي الفلوس عند الإغلاق
                    'start_time' => Carbon::now()->subHours(8), // بدأ من 8 ساعات
                    'end_time' => Carbon::now(),                // خلص دلوقتي
                    'is_late' => false,
                    'late_minutes' => 0,
                    'overtime_minutes' => 0,
                    'overtime_approved' => false,
                ]
            );
        }
    }
}
