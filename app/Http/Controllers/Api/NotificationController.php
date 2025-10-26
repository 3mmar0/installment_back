<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponse;
use App\Services\EmailNotificationService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'Notifications retrieved successfully'
        );
    }

    /**
     * Get unread notification count.
     */
    public function count(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return $this->successResponse(['count' => $count], 'Unread notifications count retrieved');
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $marked = $this->notificationService->markAsRead($id, $request->user());

        return $this->successResponse(
            ['marked' => $marked],
            $marked ? 'Notification marked as read' : 'Notification already read'
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse(['count' => $count], "$count notifications marked as read");
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
            'Notifications generated successfully'
        );
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return $this->deletedResponse('Notification deleted successfully');
    }

    /**
     * Send payment reminder emails.
     */
    public function sendReminderEmails(Request $request): JsonResponse
    {
        $result = $this->emailNotificationService->sendAllPaymentReminders($request->user());

        return $this->successResponse(
            $result,
            "Sent {$result['total_emails']} reminder emails successfully"
        );
    }
}
