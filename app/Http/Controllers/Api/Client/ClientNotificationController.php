<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponse;
use App\Models\ClientAccount;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();

        $unreadOnly = $request->boolean('unread_only');
        $notifications = $client->notifications()
            ->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->successResponse(
            NotificationResource::collection($notifications),
            'تم جلب الإشعارات بنجاح'
        );
    }

    public function count(Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();

        return $this->successResponse([
            'count' => $client->unreadNotifications()->count(),
        ], 'تم جلب عدد الإشعارات');
    }

    public function markAsRead(int $id, Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();

        $notification = $client->notifications()->findOrFail($id);

        if (! $notification->isRead()) {
            $notification->markAsRead();
        }

        return $this->successResponse(
            new NotificationResource($notification->fresh()),
            'تم تعليم الإشعار كمقروء'
        );
    }
}
