<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponse;
use App\Services\EmailNotificationService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EmailNotificationService $emailNotificationService
    ) {}

    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $unreadOnly = $request->boolean('unread_only', false);
        $notifications = $this->notificationService->getUserNotifications($request->user(), $unreadOnly);

        return $this->successResponse(
            NotificationResource::collection($notifications),
            'تم جلب الإشعارات بنجاح'
        );
    }

    /**
     * Get unread notification count.
     */
    public function count(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return $this->successResponse(['count' => $count], 'تم جلب عدد الإشعارات غير المقروءة بنجاح');
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $marked = $this->notificationService->markAsRead($id, $request->user());

        return $this->successResponse(
            ['marked' => $marked],
            $marked ? 'تم وضع علامة مقروء على الإشعار' : 'الإشعار مقروء مسبقاً'
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse(['count' => $count], "تم وضع علامة مقروء على {$count} إشعار");
    }

    /**
     * Generate notifications for upcoming and overdue payments.
     */
    public function generate(Request $request): JsonResponse
    {
        $upcomingCount = $this->notificationService->notifyUpcomingPayments($request->user());
        $overdueCount = $this->notificationService->notifyOverduePayments($request->user());

        return $this->successResponse(
            [
                'upcoming_notifications' => $upcomingCount,
                'overdue_notifications' => $overdueCount,
                'total' => $upcomingCount + $overdueCount,
            ],
            'تم إنشاء الإشعارات بنجاح'
        );
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        $deleted = DB::transaction(function () use ($notification) {
            return $notification->delete();
        });

        if ($deleted && !$request->user()->isOwner()) {
            LimitsHelper::decrementUsage($request->user()->id, 'notifications');
        }

        return $this->deletedResponse('تم حذف الإشعار بنجاح');
    }

    /**
     * Send payment reminder emails.
     */
    public function sendReminderEmails(Request $request): JsonResponse
    {
        $result = $this->emailNotificationService->sendAllPaymentReminders($request->user());

        return $this->successResponse(
            $result,
            "تم إرسال {$result['total_emails']} رسالة تذكير بنجاح"
        );
    }
}
