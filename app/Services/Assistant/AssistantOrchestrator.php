<?php

namespace App\Services\Assistant;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AssistantOrchestrator
{
    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly AssistantToolRunner $tools,
        private readonly AssistantSessionStore $sessions,
        private readonly PendingActionStore $pendingActions,
    ) {}

    /**
     * @return array{
     *   reply: string,
     *   session_id: string,
     *   pending_action: array<string, mixed>|null,
     *   navigation: array<string, mixed>|null,
     *   remaining_today: int
     * }
     */
    public function handleMessage(User $user, string $message, ?string $sessionId = null): array
    {
        if (! config('assistant.enabled', true)) {
            $this->logFail('assistant_disabled', $user, $sessionId, [
                'message_preview' => mb_substr($message, 0, 120),
            ]);

            return $this->response(
                'المساعد الذكي متوقف حالياً.',
                $sessionId ?: '',
                null,
                null,
                $this->sessions->remainingDailyMessages($user->id)
            );
        }

        if (! config('assistant.gemini.api_key')) {
            $this->logFail('gemini_api_key_missing', $user, $sessionId);

            return $this->response(
                'المساعد غير مُعد بعد. أضف مفتاح Gemini من لوحة السيرفر.',
                $sessionId ?: '',
                null,
                null,
                $this->sessions->remainingDailyMessages($user->id)
            );
        }

        if (! $this->sessions->consumeDailyMessage($user->id)) {
            $this->logFail('daily_limit_reached', $user, $sessionId, [
                'daily_limit' => (int) config('assistant.daily_message_limit', 30),
            ]);

            return $this->response(
                'وصلت للحد اليومي لرسائل المساعد. جرّب بكرة.',
                $sessionId ?: '',
                null,
                null,
                0
            );
        }

        $session = $this->sessions->load($user->id, $sessionId);
        $sessionId = $session['session_id'];
        $turns = $session['turns'];
        $turns[] = ['role' => 'user', 'text' => $message];

        $contents = $this->toGeminiContents($turns);
        $pendingAction = null;
        $navigation = null;
        $reply = '';

        try {
            for ($i = 0; $i < 4; $i++) {
                $raw = $this->gemini->generateContent(
                    $contents,
                    $this->tools->declarations(),
                    $this->systemPrompt()
                );

                $candidate = $raw['candidates'][0]['content'] ?? null;
                if (! is_array($candidate)) {
                    $this->logFail('empty_gemini_candidate', $user, $sessionId, [
                        'loop' => $i,
                        'message_preview' => mb_substr($message, 0, 120),
                    ]);
                    $reply = 'ما قدرتش أفهم الطلب. حاول بصيغة أوضح.';
                    break;
                }

                $parts = $candidate['parts'] ?? [];
                $functionCalls = [];
                $textBits = [];

                foreach ($parts as $part) {
                    if (! is_array($part)) {
                        continue;
                    }
                    if (isset($part['functionCall']['name'])) {
                        $functionCalls[] = $part['functionCall'];
                    }
                    if (isset($part['text']) && is_string($part['text']) && trim($part['text']) !== '') {
                        $textBits[] = trim($part['text']);
                    }
                }

                if ($functionCalls === []) {
                    $reply = $textBits !== []
                        ? implode("\n", $textBits)
                        : 'تم.';
                    break;
                }

                $contents[] = [
                    'role' => 'model',
                    'parts' => array_map(static function (array $call) {
                        return ['functionCall' => $call];
                    }, $functionCalls),
                ];

                $functionResponses = [];
                foreach ($functionCalls as $call) {
                    $name = (string) ($call['name'] ?? '');
                    $args = is_array($call['args'] ?? null) ? $call['args'] : [];
                    $toolResult = $this->tools->run($name, $args, $user);

                    if (! ($toolResult['ok'] ?? false)) {
                        $this->logFail('tool_run_failed', $user, $sessionId, [
                            'tool' => $name,
                            'error' => $toolResult['error'] ?? 'unknown',
                        ]);
                    }

                    if (! empty($toolResult['pending_action'])) {
                        $pendingAction = $toolResult['pending_action'];
                    }
                    if (! empty($toolResult['navigation'])) {
                        $navigation = $toolResult['navigation'];
                    }

                    $functionResponses[] = [
                        'functionResponse' => [
                            'name' => $name,
                            'response' => [
                                'ok' => (bool) ($toolResult['ok'] ?? false),
                                'error' => $toolResult['error'] ?? null,
                                'data' => $toolResult['result'] ?? null,
                                'pending_action' => $toolResult['pending_action'] ?? null,
                                'navigation' => $toolResult['navigation'] ?? null,
                            ],
                        ],
                    ];

                    // Stop looping once we have a mutation proposal — ask user to confirm.
                    if ($pendingAction) {
                        break;
                    }
                }

                $contents[] = [
                    'role' => 'user',
                    'parts' => $functionResponses,
                ];

                if ($pendingAction) {
                    $reply = $textBits !== []
                        ? implode("\n", $textBits)
                        : ('جاهز للتنفيذ: '.$pendingAction['summary_ar'].'. أكّد عشان أنفّذ.');
                    break;
                }
            }
        } catch (Throwable $e) {
            $reply = 'حصل خطأ في المساعد. حاول تاني بعد شوية.';
            $this->logFail('assistant_exception', $user, $sessionId, [
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'message_preview' => mb_substr($message, 0, 120),
            ]);
            report($e);
        }

        if ($reply === '') {
            $reply = $pendingAction
                ? ('جاهز للتنفيذ: '.$pendingAction['summary_ar'].'. أكّد عشان أنفّذ.')
                : 'تم.';
        }

        $turns[] = ['role' => 'assistant', 'text' => $reply];
        $this->sessions->save($user->id, $sessionId, $turns);

        return $this->response(
            $reply,
            $sessionId,
            $pendingAction,
            $navigation,
            $this->sessions->remainingDailyMessages($user->id)
        );
    }

    /**
     * @return array{
     *   reply: string,
     *   session_id: string|null,
     *   pending_action: null,
     *   navigation: array<string, mixed>|null,
     *   result: array<string, mixed>|null,
     *   remaining_today: int
     * }
     */
    public function confirmAction(User $user, string $pendingActionId, bool $confirmed): array
    {
        $pending = $this->pendingActions->get($user->id, $pendingActionId);
        if (! $pending) {
            $this->logFail('pending_action_expired', $user, null, [
                'pending_action_id' => $pendingActionId,
            ]);

            return [
                'reply' => 'انتهت صلاحية الطلب. ابعت الرسالة تاني.',
                'session_id' => null,
                'pending_action' => null,
                'navigation' => null,
                'result' => null,
                'remaining_today' => $this->sessions->remainingDailyMessages($user->id),
            ];
        }

        $this->pendingActions->forget($user->id, $pendingActionId);

        if (! $confirmed) {
            Log::channel('assistant')->info('assistant_action_cancelled', [
                'user_id' => $user->id,
                'pending_action_id' => $pendingActionId,
                'type' => $pending['type'] ?? null,
            ]);

            return [
                'reply' => 'تم إلغاء العملية.',
                'session_id' => null,
                'pending_action' => null,
                'navigation' => null,
                'result' => null,
                'remaining_today' => $this->sessions->remainingDailyMessages($user->id),
            ];
        }

        $executed = $this->tools->confirm($pending, $user);
        if (! ($executed['ok'] ?? false)) {
            $this->logFail('confirm_execution_failed', $user, null, [
                'pending_action_id' => $pendingActionId,
                'type' => $pending['type'] ?? null,
                'error' => $executed['error'] ?? 'unknown',
            ]);

            return [
                'reply' => (string) ($executed['error'] ?? 'فشل التنفيذ.'),
                'session_id' => null,
                'pending_action' => null,
                'navigation' => null,
                'result' => null,
                'remaining_today' => $this->sessions->remainingDailyMessages($user->id),
            ];
        }

        $type = (string) ($pending['type'] ?? '');
        $reply = match ($type) {
            'create_customer' => 'تم إضافة العميل بنجاح.',
            'create_installment' => 'تم إنشاء القسط بنجاح.',
            'pay_installment_item' => 'تم تسجيل الدفعة بنجاح.',
            default => 'تم التنفيذ بنجاح.',
        };

        return [
            'reply' => $reply,
            'session_id' => null,
            'pending_action' => null,
            'navigation' => $executed['navigation'] ?? null,
            'result' => $executed['result'] ?? null,
            'remaining_today' => $this->sessions->remainingDailyMessages($user->id),
        ];
    }

    /**
     * @return array{enabled: bool, configured: bool, remaining_today: int, daily_limit: int}
     */
    public function status(User $user): array
    {
        return [
            'enabled' => (bool) config('assistant.enabled', true),
            'configured' => (string) config('assistant.gemini.api_key') !== '',
            'remaining_today' => $this->sessions->remainingDailyMessages($user->id),
            'daily_limit' => (int) config('assistant.daily_message_limit', 30),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
أنت مساعد عربي لتطبيق أقساط (أقساطي) للتاجر. افهم اللهجة المصرية والعربية الفصحى.
تقدر تساعد في: إضافة عميل، فتح تفاصيل عميل، إنشاء قسط، سداد قسط (كامل أو جزئي)، والبحث.
قواعد مهمة:
- استخدم الأدوات المتاحة بدل ما تخترع بيانات.
- عمليات الإضافة/السداد لازم تمر عبر أدوات propose_* فقط، ومتنفذش غير بعد تأكيد المستخدم في التطبيق.
- لو الاسم مطابق لأكتر من عميل، اسأل المستخدم يختار بالـ id أو وضّح أكتر.
- لو ناقص مبلغ أو شهور أو اسم، اسأل قبل ما تقترح الإجراء.
- تاريخ البداية الافتراضي ليوم النهارده لو المستخدم ما حدّدوش.
- الردود قصيرة وواضحة بالعربي.
PROMPT;
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $turns
     * @return array<int, array<string, mixed>>
     */
    private function toGeminiContents(array $turns): array
    {
        $contents = [];
        foreach ($turns as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $turn['text']]],
            ];
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>|null  $pendingAction
     * @param  array<string, mixed>|null  $navigation
     * @return array{
     *   reply: string,
     *   session_id: string,
     *   pending_action: array<string, mixed>|null,
     *   navigation: array<string, mixed>|null,
     *   remaining_today: int
     * }
     */
    private function response(
        string $reply,
        string $sessionId,
        ?array $pendingAction,
        ?array $navigation,
        int $remaining
    ): array {
        return [
            'reply' => $reply,
            'session_id' => $sessionId,
            'pending_action' => $pendingAction,
            'navigation' => $navigation,
            'remaining_today' => $remaining,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFail(string $reason, User $user, ?string $sessionId, array $context = []): void
    {
        $payload = array_merge([
            'reason' => $reason,
            'user_id' => $user->id,
            'session_id' => $sessionId,
        ], $context);

        Log::channel('assistant')->error('assistant_fail: '.$reason, $payload);
        Log::error('[assistant] '.$reason, $payload);
    }
}
