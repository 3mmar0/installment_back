<?php

namespace App\Services\Assistant;

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

    public function remainingDailyMessages(int $userId): int
    {
        $limit = (int) config('assistant.daily_message_limit', 30);
        $used = (int) Cache::get($this->dailyKey($userId), 0);

        return max(0, $limit - $used);
    }

    public function consumeDailyMessage(int $userId): bool
    {
        $limit = (int) config('assistant.daily_message_limit', 30);
        $key = $this->dailyKey($userId);
        $used = (int) Cache::get($key, 0);
        if ($used >= $limit) {
            return false;
        }

        Cache::put($key, $used + 1, now()->endOfDay());

        return true;
    }

    private function key(int $userId, string $sessionId): string
    {
        return "assistant:session:{$userId}:{$sessionId}";
    }

    private function dailyKey(int $userId): string
    {
        return 'assistant:daily:'.$userId.':'.now()->toDateString();
    }
}
