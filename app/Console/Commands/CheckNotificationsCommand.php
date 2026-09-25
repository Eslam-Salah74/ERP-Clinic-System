<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Setup\Services\Notification\NotificationService;

class CheckNotificationsCommand extends Command
{
    protected $signature = 'notifications:check';
    protected $description = 'فحص وإرسال إشعارات المتابعات والمخزون وفقاً لإعدادات السيستم';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('بدء فحص إشعارات المتابعات والمخزون...');

        $report = $notificationService->checkAndSendNotifications();

        $this->table(
            ['البند', 'عدد الإشعارات المرسلة'],
            [
                ['إشعارات متابعات اليوم', $report['today_follow_ups_sent']],
                ['إشعارات المتابعات القادمة (مقدماً)', $report['upcoming_follow_ups_sent']],
                ['إشعارات انخفاض المخزون للأصناف', $report['low_stock_sent']],
            ]
        );

        $this->info('تم الانتهاء بنجاح عند: ' . $report['checked_at']);

        return self::SUCCESS;
    }
}
