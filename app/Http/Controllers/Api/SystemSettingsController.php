<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Jobs\BroadcastNotificationJob;
use App\Models\User;
use App\Services\QueueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly QueueManagementService $queueManagementService
    ) {}

    public function queueStatus(): JsonResponse
    {
        return $this->successResponse(
            $this->queueManagementService->getStatus(),
            'تم جلب حالة قائمة الانتظار'
        );
    }

    public function startQueue(): JsonResponse
    {
        $result = $this->queueManagementService->startWorker();

        $message = ($result['already_running'] ?? false)
            ? 'قائمة الانتظار تعمل بالفعل'
            : 'تم تشغيل قائمة الانتظار بنجاح';

        return $this->successResponse($result, $message);
    }

    public function stopQueue(): JsonResponse
    {
        $result = $this->queueManagementService->stopWorker();

        $message = $result['stopped']
            ? 'تم إيقاف قائمة الانتظار بنجاح'
            : 'قائمة الانتظار غير نشطة';

        return $this->successResponse($result, $message);
    }

    public function runQueue(): JsonResponse
    {
        $result = $this->queueManagementService->runPendingJobs();

        return $this->successResponse(
            $result,
            'تم بدء معالجة المهام المعلقة في قائمة الانتظار'
        );
    }

    public function clearCache(): JsonResponse
    {
        $result = $this->queueManagementService->clearCache();

        return $this->successResponse(
            $result,
            'تم مسح ذاكرة التخزين المؤقت بنجاح'
        );
    }

    public function users(): JsonResponse
    {
        $users = User::query()
            ->with('userLimit')
            ->where('role', UserRole::User)
            ->latest()
            ->get();

        return $this->successResponse(
            UserResource::collection($users),
            'تم جلب المستخدمين بنجاح'
        );
    }

    public function broadcastNotification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:system_announcement,mobile_app_update'],
            'action_url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', UserRole::User->value),
            ],
        ]);

        $payload = [
            'display_as' => 'modal',
        ];

        if (! empty($data['action_url'])) {
            $payload['action_url'] = $data['action_url'];
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('announcements', 'public');
            $payload['image_url'] = asset('storage/'.$path);
        } elseif (! empty($data['image_url'])) {
            $payload['image_url'] = $data['image_url'];
        }

        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'] ?? [])));
        $recipientCount = $userIds === []
            ? User::query()->where('role', UserRole::User)->count()
            : count($userIds);

        BroadcastNotificationJob::dispatch(
            $data['title'],
            $data['message'],
            $payload,
            $data['type'],
            $userIds
        );

        $typeLabel = $data['type'] === 'mobile_app_update'
            ? 'إشعار تحديث تطبيق الجوال'
            : 'إعلان عام';

        $audienceLabel = $userIds === []
            ? 'لجميع المستخدمين'
            : "لـ {$recipientCount} مستخدم";

        return $this->successResponse(
            [
                'queued' => true,
                'type' => $data['type'],
                'recipient_count' => $recipientCount,
            ],
            "تمت جدولة {$typeLabel} {$audienceLabel}"
        );
    }

    public function syncFreePlan(): JsonResponse
    {
        $result = \App\Helpers\LimitsHelper::syncAllUsersToFreePlan();

        return $this->successResponse(
            $result,
            "تم تحديث {$result['synced']} مستخدم إلى {$result['plan']}"
        );
    }
}
