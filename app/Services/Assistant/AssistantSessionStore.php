<?php

namespace App\Services\Assistant;

use App\Helpers\LimitsHelper;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AssistantSessionStore
{
    /**
     * @return array{
     *   session_id: string,
     *   turns: array<int, array{role: string, text: string}>,
     *   context: array<string, mixed>
     * }
     */
    public function load(int $userId, ?string $sessionId): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $payload = Cache::get($this->key($userId, $sessionId));

        $turns = [];
        $context = $this->defaultContext();

        if (is_array($payload)) {
            // Legacy: plain turns array
            if (array_is_list($payload)) {
                $turns = $payload;
            } else {
                $turns = is_array($payload['turns'] ?? null) ? $payload['turns'] : [];
                $context = array_merge($context, is_array($payload['context'] ?? null) ? $payload['context'] : []);
            }
        }

        $context['timezone'] = (string) (config('app.timezone') ?: 'UTC');
        $context['today'] = now($context['timezone'])->toDateString();

        return [
            'session_id' => $sessionId,
            'turns' => array_values($turns),
            'context' => $context,
        ];
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $turns
     * @param  array<string, mixed>  $context
     */
    public function save(int $userId, string $sessionId, array $turns, array $context = []): void
    {
        $limit = (int) config('assistant.session_turn_limit', 24);
        if (count($turns) > $limit) {
            $turns = array_slice($turns, -$limit);
        }

        $context = array_merge($this->defaultContext(), $context);
        $context['timezone'] = (string) (config('app.timezone') ?: 'UTC');
        $context['today'] = now($context['timezone'])->toDateString();
        $context = $this->pruneContext($context);

        Cache::put(
            $this->key($userId, $sessionId),
            [
                'turns' => array_values($turns),
                'context' => $context,
            ],
            (int) config('assistant.session_ttl', 3600)
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function mergeContext(array $context, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if ($key === 'draft' && is_array($value)) {
                $existing = is_array($context['draft'] ?? null) ? $context['draft'] : [];
                $fields = array_merge(
                    is_array($existing['fields'] ?? null) ? $existing['fields'] : [],
                    is_array($value['fields'] ?? null) ? $value['fields'] : []
                );
                $context['draft'] = array_filter([
                    'intent' => $value['intent'] ?? ($existing['intent'] ?? null),
                    'fields' => $fields,
                ], static fn ($v) => $v !== null && $v !== []);
                continue;
            }

            $context[$key] = $value;
        }

        return $this->pruneContext(array_merge($this->defaultContext(), $context));
    }

    /**
     * Compact structured memory for the model (not raw chat history).
     *
     * @param  array<string, mixed>  $context
     */
    public function contextPromptBlock(array $context): string
    {
        $lines = [];
        $tz = (string) ($context['timezone'] ?? config('app.timezone') ?: 'UTC');
        $today = (string) ($context['today'] ?? now($tz)->toDateString());
        $lines[] = "التاريخ الحالي (ميلادي): {$today}";
        $lines[] = "المنطقة الزمنية: {$tz}";

        $customer = is_array($context['selected_customer'] ?? null) ? $context['selected_customer'] : null;
        if ($customer) {
            $lines[] = sprintf(
                'العميل المختار حالياً: id=%s الاسم=%s الهاتف=%s',
                (string) ($customer['id'] ?? ''),
                (string) ($customer['name'] ?? ''),
                (string) ($customer['phone'] ?? '—')
            );
        }

        $draft = is_array($context['draft'] ?? null) ? $context['draft'] : null;
        if ($draft) {
            $intent = (string) ($draft['intent'] ?? '');
            $fields = is_array($draft['fields'] ?? null) ? $draft['fields'] : [];
            if ($intent !== '' || $fields !== []) {
                $lines[] = 'مسودة العملية الجارية (حدّث الحقول المتغيرة فقط؛ متعيدش الأسئلة من الأول):';
                if ($intent !== '') {
                    $lines[] = '- النوع: '.$intent;
                }
                foreach ($fields as $key => $value) {
                    if ($value === null || $value === '' || $value === []) {
                        continue;
                    }
                    $rendered = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                    $lines[] = "- {$key}: {$rendered}";
                }
            }
        }

        $search = is_array($context['last_search'] ?? null) ? $context['last_search'] : null;
        if ($search && ! empty($search['customers'])) {
            $lines[] = 'آخر نتائج بحث عملاء (اختَر بالـ id لو أكتر من واحد):';
            foreach (array_slice($search['customers'], 0, 8) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lines[] = sprintf(
                    '- id=%s | %s | %s',
                    (string) ($row['id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['phone'] ?? '—')
                );
            }
        }

        $summary = is_array($context['last_summary'] ?? null) ? $context['last_summary'] : null;
        if ($summary) {
            $lines[] = 'آخر ملخص أقساط مهم: '.json_encode($summary, JSON_UNESCAPED_UNICODE);
        }

        $lastAction = is_array($context['last_action_result'] ?? null) ? $context['last_action_result'] : null;
        if ($lastAction) {
            $ok = ! empty($lastAction['ok']) ? 'نجاح' : 'فشل';
            $lines[] = "آخر نتيجة تنفيذ مؤكَّد ({$ok}): ".json_encode($lastAction, JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
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

    /**
     * Check monthly assistant quota without consuming.
     */
    public function hasMonthlyQuota(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user && $user->isOwner()) {
            return true;
        }

        if (! $this->isFeatureEnabled($userId)) {
            return false;
        }

        return LimitsHelper::canCreate($userId, 'assistant_messages');
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

    /**
     * @return array<string, mixed>
     */
    private function defaultContext(): array
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');

        return [
            'timezone' => $tz,
            'today' => now($tz)->toDateString(),
            'selected_customer' => null,
            'draft' => null,
            'last_search' => null,
            'last_summary' => null,
            'last_action_result' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function pruneContext(array $context): array
    {
        if (is_array($context['last_search']['customers'] ?? null)) {
            $context['last_search']['customers'] = array_slice($context['last_search']['customers'], 0, 8);
        }

        if (is_array($context['last_summary'] ?? null)) {
            // Keep compact summary fields only.
            $summary = $context['last_summary'];
            unset($summary['plans']);
            $context['last_summary'] = $summary;
        }

        return $context;
    }

    private function key(int $userId, string $sessionId): string
    {
        return "assistant:session:{$userId}:{$sessionId}";
    }
}
