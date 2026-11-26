<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Jobs\BroadcastNotificationJob;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserLimit;
use App\Services\QueueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
            ->orderByRaw('last_active_at is null')
            ->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            UserResource::collection($users),
            'تم جلب المستخدمين بنجاح'
        );
    }

    public function announcements(): JsonResponse
    {
        $rows = Notification::query()
            ->whereIn('type', ['system_announcement', 'mobile_app_update'])
            ->whereNotNull('user_id')
            ->latest('id')
            ->limit(3000)
            ->get(['id', 'user_id', 'type', 'title', 'message', 'data', 'read_at', 'created_at']);

        $recipientRows = $rows->filter(function (Notification $notification) {
            $data = is_array($notification->data) ? $notification->data : [];

            return empty($data['is_broadcast_summary'])
                && empty($data['is_platform_admin_digest'])
                && empty($data['is_platform_admin_copy']);
        });

        $announcements = $recipientRows
            ->groupBy(fn (Notification $notification) => $this->broadcastGroupKey($notification))
            ->map(function (Collection $group, string $broadcastId) {
                /** @var Notification $first */
                $first = $group->sortBy('id')->first();
                $data = is_array($first->data) ? $first->data : [];
                $recipients = $group->count();
                $viewed = $group->filter(fn (Notification $item) => $item->read_at !== null)->count();

                return [
                    'broadcast_id' => $broadcastId,
                    'type' => $first->type,
                    'title' => $first->title,
                    'message' => $first->message,
                    'action_url' => is_string($data['action_url'] ?? null) ? $data['action_url'] : null,
                    'image_url' => is_string($data['image_url'] ?? null) ? $data['image_url'] : null,
                    'video_url' => is_string($data['video_url'] ?? null) ? $data['video_url'] : null,
                    'recipients_count' => $recipients,
                    'viewed_count' => $viewed,
                    'unseen_count' => max(0, $recipients - $viewed),
                    'created_at' => optional($group->min('created_at'))?->toIso8601String(),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return $this->successResponse(
            $announcements,
            'تم جلب الإعلانات بنجاح'
        );
    }

    public function announcementViewers(string $broadcastId): JsonResponse
    {
        $rows = Notification::query()
            ->with(['user:id,name,email'])
            ->whereIn('type', ['system_announcement', 'mobile_app_update'])
            ->whereNotNull('user_id')
            ->latest('id')
            ->limit(5000)
            ->get(['id', 'user_id', 'type', 'title', 'message', 'data', 'read_at', 'created_at']);

        $matched = $rows->filter(function (Notification $notification) use ($broadcastId) {
            $data = is_array($notification->data) ? $notification->data : [];
            if (! empty($data['is_broadcast_summary']) || ! empty($data['is_platform_admin_digest'])) {
                return false;
            }

            return $this->broadcastGroupKey($notification) === $broadcastId;
        });

        $viewers = $matched
            ->filter(fn (Notification $notification) => $notification->read_at !== null)
            ->map(fn (Notification $notification) => [
                'user_id' => $notification->user_id,
                'name' => $notification->user?->name,
                'email' => $notification->user?->email,
                'read_at' => optional($notification->read_at)?->toIso8601String(),
            ])
            ->values();

        return $this->successResponse(
            [
                'broadcast_id' => $broadcastId,
                'recipients_count' => $matched->count(),
                'viewed_count' => $viewers->count(),
                'viewers' => $viewers,
            ],
            'تم جلب مشاهدات الإعلان بنجاح'
        );
    }

    public function broadcastNotification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:system_announcement,mobile_app_update'],
            'action_url' => ['nullable', 'url', 'max:2048'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,ogg,mov,m4v', 'max:30720'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', UserRole::User->value),
            ],
        ]);

        $broadcastId = (string) Str::uuid();
        $payload = [
            'display_as' => 'modal',
            'broadcast_id' => $broadcastId,
        ];

        if (! empty($data['action_url'])) {
            $payload['action_url'] = $data['action_url'];
        }

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('announcements', 'public');
            $payload['video_url'] = asset('storage/'.$path);
        } elseif (! empty($data['video_url'])) {
            $payload['video_url'] = $data['video_url'];
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
                'broadcast_id' => $broadcastId,
            ],
            "تمت جدولة {$typeLabel} {$audienceLabel}"
        );
    }

    private function broadcastGroupKey(Notification $notification): string
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $broadcastId = $data['broadcast_id'] ?? null;

        if (is_string($broadcastId) && $broadcastId !== '') {
            return $broadcastId;
        }

        $createdAt = optional($notification->created_at)?->format('Y-m-d H:i') ?? 'unknown';

        return 'legacy-'.md5(implode('|', [
            (string) $notification->type,
            (string) $notification->title,
            (string) $notification->message,
            $createdAt,
        ]));
    }

    public function syncFreePlan(): JsonResponse
    {
        $result = \App\Helpers\LimitsHelper::syncAllUsersToFreePlan();

        return $this->successResponse(
            $result,
            "تم تحديث {$result['synced']} مستخدم إلى {$result['plan']}"
        );
    }

    public function featureStats(): JsonResponse
    {
        $limits = UserLimit::query()
            ->join('users', 'users.id', '=', 'user_limits.user_id')
            ->where('users.role', UserRole::User->value)
            ->select('user_limits.*')
            ->get();

        $totalUsers = $limits->count();

        $assistantEnabled = $limits->filter(fn (UserLimit $l) => !empty(($l->features ?? [])['assistant']))->count();
        $whatsappEnabled = $limits->filter(fn (UserLimit $l) => !empty(($l->features ?? [])['whatsapp']))->count();

        $assistantActiveUsers = $limits->filter(fn (UserLimit $l) => ($l->assistant_messages_used ?? 0) > 0)->count();
        $whatsappActiveUsers = $limits->filter(fn (UserLimit $l) => ($l->whatsapp_messages_used ?? 0) > 0)->count();

        $totalAssistantMessages = $limits->sum('assistant_messages_used');
        $totalWhatsappMessages = $limits->sum('whatsapp_messages_used');

        $whatsappCustomersEnabled = Customer::query()
            ->where('whatsapp_auto_reminders', true)
            ->count();
        $whatsappCustomersTotal = Customer::query()->count();

        return $this->successResponse([
            'total_users' => $totalUsers,
            'assistant' => [
                'enabled_count' => $assistantEnabled,
                'disabled_count' => $totalUsers - $assistantEnabled,
                'active_users' => $assistantActiveUsers,
                'total_messages' => $totalAssistantMessages,
            ],
            'whatsapp' => [
                'enabled_count' => $whatsappEnabled,
                'disabled_count' => $totalUsers - $whatsappEnabled,
                'active_users' => $whatsappActiveUsers,
                'total_messages' => $totalWhatsappMessages,
                'customers_with_auto_reminders' => $whatsappCustomersEnabled,
                'customers_total' => $whatsappCustomersTotal,
            ],
        ], 'تم جلب إحصائيات المميزات بنجاح');
    }

    public function featureUsers(Request $request): JsonResponse
    {
        $feature = $request->query('feature');
        $sortBy = $request->query('sort', 'usage');

        $query = UserLimit::query()
            ->with('user:id,name,email,phone,last_active_at')
            ->whereHas('user', fn ($q) => $q->where('role', UserRole::User->value));

        $limits = $query->get();

        $mapped = $limits->map(function (UserLimit $limit) {
            $features = $limit->features ?? [];
            $user = $limit->user;

            return [
                'user_id' => $limit->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'phone' => $user?->phone,
                'last_active_at' => $user?->last_active_at?->toISOString(),
                'subscription_name' => $limit->subscription_name,
                'assistant' => [
                    'enabled' => !empty($features['assistant']),
                    'messages_used' => $limit->assistant_messages_used ?? 0,
                    'messages_limit' => $limit->getEffectiveCap('assistant_messages'),
                    'messages_bonus' => $limit->assistant_messages_bonus ?? 0,
                ],
                'whatsapp' => [
                    'enabled' => !empty($features['whatsapp']),
                    'messages_used' => $limit->whatsapp_messages_used ?? 0,
                    'messages_limit' => $limit->getEffectiveCap('whatsapp_messages'),
                    'messages_bonus' => $limit->whatsapp_messages_bonus ?? 0,
                ],
                'usage_period' => $limit->usage_period,
            ];
        });

        if ($feature === 'assistant') {
            $mapped = $mapped->filter(fn ($u) => $u['assistant']['enabled']);
        } elseif ($feature === 'whatsapp') {
            $mapped = $mapped->filter(fn ($u) => $u['whatsapp']['enabled']);
        }

        if ($sortBy === 'usage') {
            $mapped = $mapped->sortByDesc(fn ($u) => $u['assistant']['messages_used'] + $u['whatsapp']['messages_used']);
        } else {
            $mapped = $mapped->sortBy('name');
        }

        return $this->successResponse(
            $mapped->values(),
            'تم جلب بيانات المستخدمين بنجاح'
        );
    }

    public function featureToggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => ['required', 'string', 'in:assistant,whatsapp'],
            'enabled' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ]);

        $feature = $data['feature'];
        $enabled = $data['enabled'];
        $userIds = $data['user_ids'] ?? null;

        $query = UserLimit::query()
            ->whereHas('user', fn ($q) => $q->where('role', UserRole::User->value));

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }

        $updated = 0;
        $query->chunkById(100, function ($limits) use ($feature, $enabled, &$updated) {
            foreach ($limits as $limit) {
                $features = $limit->features ?? [];
                $features[$feature] = $enabled;
                $limit->update(['features' => $features]);
                $updated++;
            }
        });

        $status = $enabled ? 'تفعيل' : 'تعطيل';
        $featureLabel = $feature === 'assistant' ? 'المساعد الذكي' : 'رسائل الواتساب';
        $scope = $userIds !== null ? "لـ {$updated} مستخدم" : "لجميع المستخدمين ({$updated})";

        return $this->successResponse(
            ['updated' => $updated, 'feature' => $feature, 'enabled' => $enabled],
            "تم {$status} {$featureLabel} {$scope}"
        );
    }
}
