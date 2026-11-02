<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PendingActionStore
{
    /**
     * @param  array<string, mixed>  $action
     */
    public function put(int $userId, array $action): string
    {
        $id = (string) Str::uuid();
        $payload = array_merge($action, [
            'id' => $id,
            'user_id' => $userId,
            'created_at' => now()->toIso8601String(),
        ]);

        Cache::put(
            $this->key($userId, $id),
            $payload,
            (int) config('assistant.pending_action_ttl', 600)
        );

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $userId, string $id): ?array
    {
        $payload = Cache::get($this->key($userId, $id));

        return is_array($payload) ? $payload : null;
    }

    public function forget(int $userId, string $id): void
    {
        Cache::forget($this->key($userId, $id));
    }

    private function key(int $userId, string $id): string
    {
        return "assistant:pending:{$userId}:{$id}";
    }
}
