<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponse;
use App\Jobs\GenerateUserPaymentNotificationsJob;
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
        GenerateUserPaymentNotificationsJob::dispatch($request->user()->id);

        return $this->successResponse(
            ['queued' => true],
            'تمت جدولة إنشاء الإشعارات بنجاح'
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
        $result = $this->emailNotificationService->queueAllPaymentReminders($request->user());

        if (($result['items_included'] ?? 0) === 0) {
            return $this->successResponse(
                $result,
                'لا توجد دفعات مستحقة أو متأخرة لإرسال تذكير لها'
            );
        }

        return $this->successResponse(
            $result,
            "تمت جدولة {$result['total_emails']} بريد إلكتروني للإرسال"
        );
    }
}
