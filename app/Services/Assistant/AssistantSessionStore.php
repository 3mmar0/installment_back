<?php

namespace App\Services\Assistant;

use App\Helpers\LimitsHelper;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AssistantSessionStore
{
    /**
     * @return array{session_id: string, turns: array<int, array{role: string, text: string}>}
     */
    public function load(int $userId, ?string $sessionId): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $turns = Cache::get($this->key($userId, $sessionId), []);

        if (! is_array($turns)) {
            $turns = [];
        }

        return [
            'session_id' => $sessionId,
            'turns' => array_values($turns),
        ];
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $turns
     */
    public function save(int $userId, string $sessionId, array $turns): void
    {
        $limit = (int) config('assistant.session_turn_limit', 10);
        if (count($turns) > $limit) {
            $turns = array_slice($turns, -$limit);
        }

        Cache::put(
            $this->key($userId, $sessionId),
            array_values($turns),
            (int) config('assistant.session_ttl', 3600)
        );
    }

    public function isFeatureEnabled(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user && $user->isOwner()) {
            return true;
        }

        return LimitsHelper::canAccessFeature($userId, 'assistant');
    }

    public function monthlyLimit(int $userId): int
    {
        $user = User::query()->find($userId);
        if ($user && $user->isOwner()) {
            return PHP_INT_MAX;
        }

        $remaining = LimitsHelper::getRemainingCount($userId, 'assistant_messages');
        $used = 0;
        $info = LimitsHelper::getSubscriptionInfo($userId);
        if ($info) {
            $used = (int) ($info['usage']['assistant_messages_used'] ?? 0);
        }

        if ($remaining === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return $used + $remaining;
    }

    public function remainingMonthlyMessages(int $userId): int
    {
        $user = User::query()->find($userId);
        if ($user && $user->isOwner()) {
            return PHP_INT_MAX;
        }

        if (! $this->isFeatureEnabled($userId)) {
            return 0;
        }

        return LimitsHelper::getRemainingCount($userId, 'assistant_messages');
    }

    public function consumeMonthlyMessage(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user && $user->isOwner()) {
            return true;
        }

        if (! $this->isFeatureEnabled($userId)) {
            return false;
        }

        if (! LimitsHelper::canCreate($userId, 'assistant_messages')) {
            return false;
        }

        return LimitsHelper::incrementUsage($userId, 'assistant_messages');
    }

    /** @deprecated Use remainingMonthlyMessages */
    public function remainingDailyMessages(int $userId): int
    {
        return $this->remainingMonthlyMessages($userId);
    }

    /** @deprecated Use consumeMonthlyMessage */
    public function consumeDailyMessage(int $userId): bool
    {
        return $this->consumeMonthlyMessage($userId);
    }

    private function key(int $userId, string $sessionId): string
    {
        return "assistant:session:{$userId}:{$sessionId}";
    }
}
