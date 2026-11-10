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
            'status' => 'pending',
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

    /**
     * Atomically claim a pending action for execution.
     * Returns null if missing; returns existing payload if already executed/processing.
     *
     * @return array{claimed: bool, action: array<string, mixed>}|null
     */
    public function claim(int $userId, string $id): ?array
    {
        $lock = Cache::lock($this->lockKey($userId, $id), 15);

        try {
            if (! $lock->block(5)) {
                $existing = $this->get($userId, $id);
                if (! $existing) {
                    return null;
                }

                return ['claimed' => false, 'action' => $existing];
            }

            $payload = $this->get($userId, $id);
            if (! $payload) {
                return null;
            }

            $status = (string) ($payload['status'] ?? 'pending');
            if ($status !== 'pending') {
                return ['claimed' => false, 'action' => $payload];
            }

            $payload['status'] = 'processing';
            $payload['processing_at'] = now()->toIso8601String();
            Cache::put(
                $this->key($userId, $id),
                $payload,
                (int) config('assistant.pending_action_ttl', 600)
            );

            return ['claimed' => true, 'action' => $payload];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markExecuted(int $userId, string $id, array $result, bool $ok): void
    {
        $payload = $this->get($userId, $id) ?? [
            'id' => $id,
            'user_id' => $userId,
        ];

        $payload['status'] = $ok ? 'executed' : 'failed';
        $payload['executed_at'] = now()->toIso8601String();
        $payload['execution_result'] = $result;
        $payload['execution_ok'] = $ok;

        // Keep executed/failed receipts longer than pending TTL so retries stay idempotent.
        Cache::put(
            $this->key($userId, $id),
            $payload,
            (int) config('assistant.pending_action_result_ttl', 3600)
        );
    }

    public function markCancelled(int $userId, string $id): void
    {
        $payload = $this->get($userId, $id);
        if (! $payload) {
            return;
        }

        $payload['status'] = 'cancelled';
        $payload['cancelled_at'] = now()->toIso8601String();
        Cache::put(
            $this->key($userId, $id),
            $payload,
            (int) config('assistant.pending_action_result_ttl', 3600)
        );
    }

    public function forget(int $userId, string $id): void
    {
        Cache::forget($this->key($userId, $id));
    }

    private function key(int $userId, string $id): string
    {
        return "assistant:pending:{$userId}:{$id}";
    }

    private function lockKey(int $userId, string $id): string
    {
        return "assistant:pending-lock:{$userId}:{$id}";
    }
}
