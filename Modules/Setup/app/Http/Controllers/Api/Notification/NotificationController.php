<?php

namespace Modules\Setup\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Support\API;
use Illuminate\Http\Request;
use Modules\Setup\Services\Notification\NotificationService;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * عرض قائمة إشعارات المستخدم الحالي
     */
    public function index(Request $request)
    {
        return $this->notificationService->index($request);
    }

    /**
     * عدد الإشعارات غير المقروءة
     */
    public function unreadCount()
    {
        return $this->notificationService->unreadCount();
    }

    /**
     * تحديد إشعار كمقروء
     */
    public function markAsRead($id)
    {
        return $this->notificationService->markAsRead($id);
    }

    /**
     * تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead()
    {
        return $this->notificationService->markAllAsRead();
    }

    /**
     * حذف إشعار
     */
    public function destroy($id)
    {
        return $this->notificationService->destroy($id);
    }

    /**
     * تشغيل فحص الإشعارات يدوياً (متابعات + مخزون)
     */
    public function check()
    {
        $report = $this->notificationService->checkAndSendNotifications();

        return API::newInstance()
            ->isOk('Notifications check completed successfully')
            ->setData($report)
            ->build();
    }
}
