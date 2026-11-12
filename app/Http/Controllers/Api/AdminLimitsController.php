<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserLimitResource;
use App\Http\Traits\ApiResponse;
use App\Models\MerchantGift;
use App\Models\User;
use App\Models\UserLimit;
use App\Services\PlatformWhatsAppQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLimitsController extends Controller
{
    use ApiResponse;

    /**
     * List merchant subscriptions / active user limits.
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 100)));
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $query = UserLimit::query()
            ->select('user_limits.*')
            ->join('users', 'users.id', '=', 'user_limits.user_id')
            ->with(['user:id,name,email,phone,role,last_active_at'])
            ->where('users.role', UserRole::User)
            ->orderByRaw('users.last_active_at is null')
            ->orderByDesc('users.last_active_at')
            ->orderByDesc('user_limits.id');

        if (is_string($status) && $status !== '') {
            $query->where('user_limits.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('user_limits.subscription_name', 'like', "%{$search}%")
                    ->orWhere('user_limits.subscription_slug', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            UserLimitResource::collection($paginator),
            'تم جلب اشتراكات المستخدمين'
        );
    }

    /**
     * Messaging usage summary for merchants + platform Meta balance.
     */
    public function messagingLimits(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 100)));
        $search = trim((string) $request->query('search', ''));

        $query = UserLimit::query()
            ->select('user_limits.*')
            ->join('users', 'users.id', '=', 'user_limits.user_id')
            ->with(['user:id,name,email,phone,role,last_active_at'])
            ->where('users.role', UserRole::User)
            ->orderByRaw('users.last_active_at is null')
            ->orderByDesc('users.last_active_at')
            ->orderByDesc('user_limits.id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $rows = collect($paginator->items())->map(function (UserLimit $limit) {
            $assistantCap = $limit->getEffectiveCap('assistant_messages');
            $whatsappCap = $limit->getEffectiveCap('whatsapp_messages');
            $features = is_array($limit->features) ? $limit->features : [];

            return [
                'user_id' => $limit->user_id,
                'user' => $limit->user ? [
                    'id' => $limit->user->id,
                    'name' => $limit->user->name,
                    'email' => $limit->user->email,
                ] : null,
                'subscription_name' => $limit->subscription_name,
                'status' => $limit->status,
                'features' => [
                    'assistant' => (bool) ($features['assistant'] ?? $features['all'] ?? false),
                    'whatsapp' => (bool) ($features['whatsapp'] ?? $features['all'] ?? false),
                ],
                'assistant' => [
                    'cap' => $assistantCap,
                    'used' => (int) ($limit->assistant_messages_used ?? 0),
                    'bonus' => (int) ($limit->assistant_messages_bonus ?? 0),
                    'remaining' => $limit->getRemainingCount('assistant_messages'),
                ],
                'whatsapp' => [
                    'cap' => $whatsappCap,
                    'used' => (int) ($limit->whatsapp_messages_used ?? 0),
                    'bonus' => (int) ($limit->whatsapp_messages_bonus ?? 0),
                    'remaining' => $limit->getRemainingCount('whatsapp_messages'),
                ],
            ];
        });

        return $this->successResponse([
            'platform_whatsapp' => PlatformWhatsAppQuota::snapshot(),
            'merchants' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ], 'تم جلب حدود الرسائل');
    }

    public function platformWhatsApp(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return $this->successResponse(
                PlatformWhatsAppQuota::snapshot(),
                'تم جلب رصيد واتس المنصة'
            );
        }

        $data = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'used' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
        ]);

        if (array_key_exists('limit', $data)) {
            PlatformWhatsAppQuota::setLimit((int) $data['limit']);
        }
        if (array_key_exists('used', $data)) {
            PlatformWhatsAppQuota::setUsed((int) $data['used']);
        }

        return $this->successResponse(
            PlatformWhatsAppQuota::snapshot(),
            'تم تحديث رصيد واتس المنصة'
        );
    }

    public function giftsIndex(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 100)));
        $resource = $request->query('resource');
        $userId = $request->query('user_id');

        $query = MerchantGift::query()
            ->with([
                'user:id,name,email',
                'granter:id,name,email',
            ])
            ->orderByDesc('id');

        if (is_string($resource) && in_array($resource, MerchantGift::RESOURCES, true)) {
            $query->where('resource', $resource);
        }
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        return $this->successResponse(
            $query->paginate($perPage),
            'تم جلب سجل الهدايا'
        );
    }

    public function giftsBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'resource' => ['required', 'string', Rule::in(MerchantGift::RESOURCES)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:500'],
            'source' => ['sometimes', 'string', Rule::in(['gift', 'admin_adjust'])],
        ]);

        $gifts = [];
        foreach (array_unique(array_map('intval', $data['user_ids'])) as $userId) {
            $gifts[] = LimitsHelper::grantGift(
                $userId,
                $data['resource'],
                (int) $data['amount'],
                $request->user()?->id,
                $data['note'] ?? null,
                $data['source'] ?? 'gift'
            );
        }

        return $this->successResponse([
            'granted' => count($gifts),
            'gifts' => $gifts,
        ], 'تم منح الهدايا بنجاح');
    }
}
