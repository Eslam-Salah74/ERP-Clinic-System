<?php

namespace Modules\Reception\Services\Shift;

use App\Support\API;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Reception\Enums\PaymentMethodEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Enums\TransactionTypeEnum;
use Modules\Reception\Filters\Shift\ShiftFilter;
use Modules\Reception\Http\Resources\Shift\ShiftResource;
use Modules\Reception\Models\Shift;
use Modules\Reception\Models\Transaction;
use Modules\Setup\Models\Setting;

class ShiftService
{
    public function index($request, ShiftFilter $filter)
    {
        $data = Shift::filter($filter)->with('user')->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(ShiftResource::collection($data))->build();
    }

    public function openShift($request)
    {
        $userId = Auth::id();

        // 1. التأكد أن المستخدم ليس لديه شفت مفتوح بالفعل
        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if ($activeShift) {
            return API::newInstance()->isError('لديك شفت مفتوح بالفعل ولا يمكنك فتح شفت جديد حتى يتم إغلاقه.')->build();
        }

        // 2. التحقق من إعدادات الـ GPS (هل هو مفعل من الإعدادات أم لا؟)
        $isGpsEnabled = filter_var(Setting::where('key', 'enable_gps_attendance')->value('value') ?? true, FILTER_VALIDATE_BOOLEAN);

        $isLate = false;
        $lateMinutes = 0;

        if ($isGpsEnabled) {
            $lat = $request->input('latitude');
            $lng = $request->input('longitude');

            if (!$lat || !$lng) {
                return API::newInstance()->isError('يرجى تفعيل موقع الجي بي إس (GPS) وإرسال الإحداثيات لفتح الشفت.')->build();
            }

            // جلب إحداثيات العيادة والمدى المسموح من جدول الإعدادات
            $clinicLat = (float) (Setting::where('key', 'clinic_latitude')->value('value') ?? 30.044420);
            $clinicLng = (float) (Setting::where('key', 'clinic_longitude')->value('value') ?? 31.235712);
            $allowedRadius = (float) (Setting::where('key', 'clinic_radius_meters')->value('value') ?? 50); // بالمتر

            // حساب المسافة الفعلية باستخدام معادلة Haversine
            $distance = $this->calculateDistance($lat, $lng, $clinicLat, $clinicLng);

            if ($distance > $allowedRadius) {
                return API::newInstance()->isError("أنت خارج النطاق الجغرافي للعيادة! المسافة بينك وبين المركز حوالي " . round($distance) . " متر والمسموح هو {$allowedRadius} متر فقط.")->build();
            }
        }

        // 3. حساب التأخير
        $startTime = Carbon::now();
        $officialStartTime = Carbon::today()->setHour(8)->setMinute(0);

        if ($startTime->greaterThan($officialStartTime)) {
            $isLate = true;
            $lateMinutes = $officialStartTime->diffInMinutes($startTime);
        }

        // 4. إنشاء الشفت الجديد
        $shift = Shift::create([
            'user_id' => $userId,
            'status' => ShiftStatusEnum::OPEN->value,
            'initial_balance' => $request->input('initial_balance', 0),
            'start_time' => $startTime,
            'opening_latitude' => $request->input('latitude'),
            'opening_longitude' => $request->input('longitude'),
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
        ]);

        return API::newInstance()
            ->isCreated('تم فتح الشفت بنجاح')
            ->setData(new ShiftResource($shift->load('user')))
            ->build();
    }

    public function closeShift($id, $request)
    {
        $shift = Shift::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$shift || $shift->status === ShiftStatusEnum::CLOSED->value) {
            return API::newInstance()->isError('الشفت غير موجود أو مغلق بالفعل.')->build();
        }

        $endTime = Carbon::now();
        $overtimeMinutes = 0;

        // حساب الـ Overtime لو تأخر عن الساعة 4 عصراً (16:00)
        $officialEndTime = Carbon::parse($shift->start_time)->copy()->setHour(16)->setMinute(0);
        if ($endTime->greaterThan($officialEndTime)) {
            $overtimeMinutes = $officialEndTime->diffInMinutes($endTime);
        }

        // --- الحسبة المالية الاحترافية للشفت ---
        // 1. إجمالي الكاش الذي دخل الخزنة خلال هذا الشفت
        $totalCashIncome = Transaction::where('shift_id', $shift->id)
            ->where('payment_method', PaymentMethodEnum::CASH->value)
            ->where('type', TransactionTypeEnum::INCOME->value)
            ->sum('amount');

        // 2. إجمالي الكاش الذي خرج (مرتجعات) خلال هذا الشفت
        $totalCashRefund = Transaction::where('shift_id', $shift->id)
            ->where('payment_method', PaymentMethodEnum::CASH->value)
            ->where('type', TransactionTypeEnum::REFUND->value)
            ->sum('amount');

        // المبلغ المفترض وجوده في الدرج = (عهدة البداية) + (إجمالي الإيرادات الكاش) - (إجمالي المرتجعات الكاش)
        $expectedBalance = ($shift->initial_balance + $totalCashIncome) - $totalCashRefund;

        // المبلغ الفعلي الذي سلمه الموظف وعاد يكتبه في الطلب
        $actualBalance = (float) $request->input('final_balance', 0);

        // حساب العجز أو الزيادة
        $difference = $actualBalance - $expectedBalance;
        // لو بالموجب تبقى (زيادة)، لو بالسالب تبقى (عجز)

        // تحديث بيانات الشفت
        $shift->update([
            'status' => ShiftStatusEnum::CLOSED->value,
            'end_time' => $endTime,
            'final_balance' => $actualBalance,
            'overtime_minutes' => $overtimeMinutes,
        ]);

        return API::newInstance()
            ->isOk('تم إغلاق الشفت بنجاح')
            ->setData([
                'shift' => new ShiftResource($shift),
                'financial_summary' => [
                    'initial_balance' => (float) $shift->initial_balance,
                    'total_cash_income' => (float) $totalCashIncome,
                    'total_cash_refund' => (float) $totalCashRefund,
                    'expected_balance' => (float) $expectedBalance,
                    'actual_balance' => (float) $actualBalance,
                    'difference' => (float) $difference, // العجز أو الزيادة بالقرش
                    'status_note' => $difference == 0 ? 'الدرج مضبوط تماماً بالقرش' : ($difference > 0 ? 'يوجد زيادة في الدرج بقيمة ' . $difference : 'يوجد عجز في الدرج بقيمة ' . abs($difference))
                ]
            ])
            ->build();
    }

    public function show($id)
    {
        $record = Shift::with('user')->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new ShiftResource($record))->build();
    }

    // معادلة حساب المسافة الجغرافية بالأمتار (Haversine Formula)
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
