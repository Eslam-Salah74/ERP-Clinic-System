<?php

namespace Modules\Setup\Services\Notification;

use App\Enums\UserType;
use App\Models\User;
use App\Support\API;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Notifications\ItemLowStockNotification;
use Modules\Reception\Enums\FollowUpStatusEnum;
use Modules\Reception\Models\FollowUp;
use Modules\Reception\Notifications\FollowUpReminderNotification;
use Modules\Setup\Http\Resources\Notification\NotificationResource;
use Modules\Setup\Models\Setting;

class NotificationService
{
    /**
     * جلب إشعارات المستخدم الحالي مع الفلترة والصفحات
     */
    public function index($request)
    {
        $user = Auth::user();
        if (!$user) {
            return API::newInstance()->isError('Unauthenticated', 401)->build();
        }

        // فحص سريع وتحديث تلقائي للإشعارات لحظياً دون الحاجة لكرون جوب
        try {
            $this->checkFollowUps();
        } catch (\Throwable $e) {
            // Silently continue
        }

        $query = $user->notifications();

        // فلترة غير المقروءة فقط
        if ($request->boolean('unread_only')) {
            $query = $user->unreadNotifications();
        }

        // فلترة بنوع الإشعار (follow_up, low_stock, إلخ)
        if ($type = $request->get('type')) {
            $query->whereJsonContains('data->type', $type);
        }

        $perPage = (int) $request->get('per_page', 15);
        $notifications = ($request->boolean('all') || $request->get('paginate') === 'false' || $perPage === -1)
            ? $query->get()
            : $query->paginate($perPage);

        return API::newInstance()
            ->isOk('Notifications retrieved successfully')
            ->setData(NotificationResource::collection($notifications))
            ->build();
    }

    /**
     * عدد الإشعارات غير المقروءة للمستخدم الحالي
     */
    public function unreadCount()
    {
        $user = Auth::user();
        if (!$user) {
            return API::newInstance()->isError('Unauthenticated', 401)->build();
        }

        // فحص سريع وتحديث تلقائي للإشعارات لحظياً دون الحاجة لكرون جوب
        try {
            $this->checkFollowUps();
        } catch (\Throwable $e) {
            // Silently continue
        }

        $count = $user->unreadNotifications()->count();

        return API::newInstance()
            ->isOk('Unread count retrieved successfully')
            ->setData(['unread_count' => $count])
            ->build();
    }

    /**
     * تحديد إشعار معين كمقروء
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        if (!$user) {
            return API::newInstance()->isError('Unauthenticated', 401)->build();
        }

        $notification = $user->notifications()->where('id', $id)->first();
        if (!$notification) {
            return API::newInstance()->isError('Notification not found', 404)->build();
        }

        $notification->markAsRead();

        return API::newInstance()
            ->isOk('Notification marked as read successfully')
            ->setData(new NotificationResource($notification))
            ->build();
    }

    /**
     * تحديد جميع الإشعارات كمقروءة للمستخدم الحالي
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        if (!$user) {
            return API::newInstance()->isError('Unauthenticated', 401)->build();
        }

        $user->unreadNotifications->markAsRead();

        return API::newInstance()
            ->isOk('All notifications marked as read successfully')
            ->build();
    }

    /**
     * حذف إشعار
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return API::newInstance()->isError('Unauthenticated', 401)->build();
        }

        $notification = $user->notifications()->where('id', $id)->first();
        if (!$notification) {
            return API::newInstance()->isError('Notification not found', 404)->build();
        }

        $notification->delete();

        return API::newInstance()
            ->isOk('Notification deleted successfully')
            ->build();
    }

    /**
     * فحص وإرسال إشعارات المتابعات والمخزون وفقاً لإعدادات السيستم
     */
    public function checkAndSendNotifications(): array
    {
        $followUpResults = $this->checkFollowUps();
        $lowStockResults = $this->checkLowStockItems();

        return [
            'today_follow_ups_sent'    => $followUpResults['today_sent'],
            'upcoming_follow_ups_sent' => $followUpResults['upcoming_sent'],
            'low_stock_sent'           => $lowStockResults,
            'checked_at'               => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * فحص المتابعات (متابعات اليوم + متابعات قادمة قبلها بأيام محددة في الإعدادات)
     */
    public function checkFollowUps(): array
    {
        $todaySent = 0;
        $upcomingSent = 0;

        // 1. فحص إشعارات متابعات اليوم
        $notifyTodaySetting = Setting::where('key', 'follow_up_notify_today')->value('value');
        $isTodayEnabled = is_null($notifyTodaySetting) || filter_var($notifyTodaySetting, FILTER_VALIDATE_BOOLEAN);

        if ($isTodayEnabled) {
            $todayFollowUps = FollowUp::with(['patient', 'doctor'])
                ->where('status', FollowUpStatusEnum::PENDING->value)
                ->whereDate('follow_up_date', Carbon::today())
                ->get();

            foreach ($todayFollowUps as $followUp) {
                $todaySent += $this->sendFollowUpNotification($followUp, 'today', 0);
            }
        }

        // 2. فحص إشعارات المتابعات قبلها بعدد أيام محدد في الإعدادات
        $daysBefore = (int) (Setting::where('key', 'follow_up_reminder_days_before')->value('value') ?? 1);

        if ($daysBefore > 0) {
            $targetDate = Carbon::today()->addDays($daysBefore)->toDateString();
            $upcomingFollowUps = FollowUp::with(['patient', 'doctor'])
                ->where('status', FollowUpStatusEnum::PENDING->value)
                ->whereDate('follow_up_date', $targetDate)
                ->get();

            foreach ($upcomingFollowUps as $followUp) {
                $upcomingSent += $this->sendFollowUpNotification($followUp, 'upcoming', $daysBefore);
            }
        }

        return [
            'today_sent'    => $todaySent,
            'upcoming_sent' => $upcomingSent,
        ];
    }

    /**
     * إرسال إشعار متابعة لمستحقيها (الطبيب المسؤول + الاستقبال + المديرين)
     */
    protected function sendFollowUpNotification(FollowUp $followUp, string $reminderType, int $daysBefore): int
    {
        $recipients = collect();

        // الطبيب المعالج
        if ($followUp->doctor && $followUp->doctor->is_active) {
            $recipients->push($followUp->doctor);
        }

        // موظفي الاستقبال والمديرين
        $staff = User::where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('type', [UserType::ADMIN->value, UserType::RECEPTIONIST->value])
                  ->orWhereHas('roles', function ($rq) {
                      $rq->whereIn('name', ['admin', 'super-admin', 'receptionist']);
                  });
            })
            ->get();

        foreach ($staff as $user) {
            $recipients->push($user);
        }

        $recipients = $recipients->unique('id');
        $sentCount = 0;

        foreach ($recipients as $recipient) {
            // منع تكرار الإشعار لنفس المتابعة ونفس النوع في نفس اليوم
            $alreadyNotified = $recipient->notifications()
                ->whereJsonContains('data->type', 'follow_up')
                ->whereJsonContains('data->reminder_type', $reminderType)
                ->whereJsonContains('data->follow_up_id', $followUp->id)
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if (!$alreadyNotified) {
                $recipient->notify(new FollowUpReminderNotification($followUp, $reminderType, $daysBefore));
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * فحص جميع الأصناف التي وصل مخزونها للحد الأدنى المحدد في الإعدادات
     */
    public function checkLowStockItems(): int
    {
        $threshold = (float) (Setting::where('key', 'item_low_stock_threshold')->value('value') ?? 5);

        $lowStockItems = Item::where('is_active', true)
            ->where('current_stock', '<=', $threshold)
            ->get();

        $totalSent = 0;
        foreach ($lowStockItems as $item) {
            $totalSent += $this->notifyLowStockForItem($item, $threshold);
        }

        return $totalSent;
    }

    /**
     * إرسال إشعار انخفاض مخزون لصنف معين
     */
    public function notifyLowStockForItem(Item $item, ?float $threshold = null): int
    {
        if ($threshold === null) {
            $threshold = (float) (Setting::where('key', 'item_low_stock_threshold')->value('value') ?? 5);
        }

        if ($item->current_stock > $threshold) {
            return 0;
        }

        // المستلمون: المديرين والمحاسبين ومسؤولي المخازن
        $recipients = User::where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('type', [UserType::ADMIN->value, UserType::ACCOUNTANT->value])
                  ->orWhereHas('roles', function ($rq) {
                      $rq->whereIn('name', ['admin', 'super-admin', 'accountant', 'inventory']);
                  });
            })
            ->get();

        $sentCount = 0;
        foreach ($recipients as $recipient) {
            // منع إرسال إشعار مكرر غير مقروء لنفس الصنف في نفس اليوم
            $alreadyNotified = $recipient->notifications()
                ->whereJsonContains('data->type', 'low_stock')
                ->whereJsonContains('data->item_id', $item->id)
                ->whereNull('read_at')
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if (!$alreadyNotified) {
                $recipient->notify(new ItemLowStockNotification($item, $threshold));
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * فحص فوري لمتابعة واحدة فور إنشائها أو تعديلها
     */
    public function checkSingleFollowUp(FollowUp $followUp): void
    {
        if ($followUp->status !== FollowUpStatusEnum::PENDING && $followUp->status !== FollowUpStatusEnum::PENDING->value) {
            return;
        }

        $date = $followUp->follow_up_date ? Carbon::parse($followUp->follow_up_date)->toDateString() : null;
        if (!$date) {
            return;
        }

        // 1. هل المتابعة مجدولة لليوم؟
        $today = Carbon::today()->toDateString();
        $notifyTodaySetting = Setting::where('key', 'follow_up_notify_today')->value('value');
        $isTodayEnabled = is_null($notifyTodaySetting) || filter_var($notifyTodaySetting, FILTER_VALIDATE_BOOLEAN);

        if ($date === $today && $isTodayEnabled) {
            $this->sendFollowUpNotification($followUp, 'today', 0);
            return;
        }

        // 2. هل المتابعة قادمة بعد عدد الأيام المحدد في الإعدادات؟
        $daysBefore = (int) (Setting::where('key', 'follow_up_reminder_days_before')->value('value') ?? 1);
        if ($daysBefore > 0) {
            $upcomingDate = Carbon::today()->addDays($daysBefore)->toDateString();
            if ($date === $upcomingDate) {
                $this->sendFollowUpNotification($followUp, 'upcoming', $daysBefore);
            }
        }
    }
}
