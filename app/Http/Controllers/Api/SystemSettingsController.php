<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Jobs\BroadcastNotificationJob;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\QueueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        BroadcastNotificationJob::dispatch($data['title'], $data['message']);

        return $this->successResponse(
            ['queued' => true],
            'تمت جدولة إرسال الإشعار لجميع المستخدمين'
        );
    }
}
