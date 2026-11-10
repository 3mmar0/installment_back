<?php

namespace App\Services\Assistant;

use App\Models\User;
use Illuminate\Support\Facades\Log;
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
                $this->sessions->remainingMonthlyMessages($user->id)
            );
        }

        if (! config('assistant.gemini.api_key')) {
            $this->logFail('gemini_api_key_missing', $user, $sessionId);

            return $this->response(
                'المساعد غير مُعد بعد. أضف مفتاح Gemini من لوحة السيرفر.',
                $sessionId ?: '',
                null,
                null,
                $this->sessions->remainingMonthlyMessages($user->id)
            );
        }

        if (! $this->sessions->isFeatureEnabled($user->id)) {
            $this->logFail('assistant_feature_disabled', $user, $sessionId);

            return $this->response(
                'المساعد الذكي غير متاح في خطتك الحالية.',
                $sessionId ?: '',
                null,
                null,
                0
            );
        }

        if (! $this->sessions->hasMonthlyQuota($user->id)) {
            $this->logFail('monthly_limit_reached', $user, $sessionId, [
                'remaining' => $this->sessions->remainingMonthlyMessages($user->id),
            ]);

            return $this->response(
                'وصلت للحد الشهري لرسائل المساعد في خطتك.',
                $sessionId ?: '',
                null,
                null,
                0
            );
        }

        $session = $this->sessions->load($user->id, $sessionId);
        $sessionId = $session['session_id'];
        $turns = $session['turns'];
        $context = $session['context'];
        $turns[] = ['role' => 'user', 'text' => $message];

        $contents = $this->toGeminiContents($turns);
        $pendingAction = null;
        $navigation = null;
        $reply = '';
        $sawFailedTool = false;
        $billableReadSuccess = false;

        try {
            for ($i = 0; $i < 6; $i++) {
                $raw = $this->gemini->generateContent(
                    $contents,
                    $this->tools->declarations(),
                    $this->systemPrompt($context)
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
                        : ($sawFailedTool
                            ? 'ما قدرتش أنفّذ الطلب بالكامل. راجع البيانات وحاول تاني.'
                            : 'تم.');
                    break;
                }

                // Echo the model parts verbatim so Gemini thought signatures
                // attached to functionCall parts are preserved on the next turn.
                $contents[] = [
                    'role' => 'model',
                    'parts' => array_values(array_filter(
                        $parts,
                        static fn ($part) => is_array($part)
                    )),
                ];

                $functionResponses = [];
                foreach ($functionCalls as $call) {
                    $name = (string) ($call['name'] ?? '');
                    $args = is_array($call['args'] ?? null) ? $call['args'] : [];
                    $toolResult = $this->tools->run($name, $args, $user, $sessionId);

                    if (! ($toolResult['ok'] ?? false)) {
                        $sawFailedTool = true;
                        $this->logFail('tool_run_failed', $user, $sessionId, [
                            'tool' => $name,
                            'error' => $toolResult['error'] ?? 'unknown',
                        ]);
                    } elseif ($this->isBillableReadTool($name)) {
                        $billableReadSuccess = true;
                    }

                    $context = $this->sessions->mergeContext(
                        $context,
                        $this->contextPatchFromTool($name, $args, $toolResult)
                    );

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
                                'status' => $toolResult['result']['status'] ?? null,
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
            $error = $e->getMessage();
            $safeError = preg_replace('/([?&]key=)[^&\s]+/i', '$1[REDACTED]', $error) ?? $error;
            $reply = str_contains(strtolower($safeError), 'ssl') || str_contains(strtolower($safeError), 'curl error 60')
                ? 'المساعد مش قادر يتصل بخدمة الذكاء الاصطناعي حالياً (مشكلة شهادة SSL على السيرفر). حاول تاني بعد شوية.'
                : 'حصل خطأ في المساعد. حاول تاني بعد شوية.';
            $this->logFail('assistant_exception', $user, $sessionId, [
                'exception' => $e::class,
                'error' => $safeError,
                'message_preview' => mb_substr($message, 0, 120),
            ]);
            report($e);
        }

        if ($reply === '') {
            if ($pendingAction) {
                $reply = 'جاهز للتنفيذ: '.$pendingAction['summary_ar'].'. أكّد عشان أنفّذ.';
            } elseif ($sawFailedTool) {
                $reply = 'ما قدرتش أنفّذ الطلب. حاول تاني ببيانات أوضح.';
            } else {
                $reply = 'تم.';
            }
        }

        // Never claim success when the only signal is a failed tool.
        if ($sawFailedTool && ! $pendingAction && $this->looksLikeFalseSuccess($reply)) {
            $reply = 'ما قدرتش أنفّذ الطلب بالكامل. راجع البيانات وحاول تاني.';
        }

        // Charge once for a successful read/nav outcome in this turn.
        // Pending mutations charge later on successful confirm only.
        if ($pendingAction === null && $billableReadSuccess) {
            $this->sessions->consumeMonthlyMessage($user->id);
        }

        $turns[] = ['role' => 'assistant', 'text' => $reply];
        $this->sessions->save($user->id, $sessionId, $turns, $context);

        return $this->response(
            $reply,
            $sessionId,
            $pendingAction,
            $navigation,
            $this->sessions->remainingMonthlyMessages($user->id)
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
    public function confirmAction(User $user, string $pendingActionId, bool $confirmed, ?string $sessionId = null): array
    {
        if ($confirmed && ! $this->sessions->hasMonthlyQuota($user->id)) {
            $this->logFail('monthly_limit_reached_on_confirm', $user, $sessionId, [
                'pending_action_id' => $pendingActionId,
            ]);

            return $this->confirmResponse(
                $user,
                'وصلت للحد الشهري لرسائل المساعد في خطتك.',
                $sessionId,
                null,
                null
            );
        }

        $claimed = $this->pendingActions->claim($user->id, $pendingActionId);
        if (! $claimed) {
            $this->logFail('pending_action_expired', $user, $sessionId, [
                'pending_action_id' => $pendingActionId,
            ]);

            return $this->confirmResponse(
                $user,
                'انتهت صلاحية الطلب. ابعت الرسالة تاني.',
                $sessionId,
                null,
                null
            );
        }

        $pending = $claimed['action'];
        $status = (string) ($pending['status'] ?? 'pending');

        // Idempotent replay: already finished.
        if (in_array($status, ['executed', 'failed', 'cancelled'], true) && ! $claimed['claimed']) {
            if ($status === 'cancelled') {
                return $this->confirmResponse($user, 'تم إلغاء العملية.', $sessionId, null, null);
            }

            $cached = is_array($pending['execution_result'] ?? null) ? $pending['execution_result'] : [];
            $ok = (bool) ($pending['execution_ok'] ?? false);
            $reply = $ok
                ? (string) ($cached['reply'] ?? $this->successReplyForType((string) ($pending['type'] ?? '')))
                : (string) ($cached['error'] ?? 'فشل التنفيذ.');

            return $this->confirmResponse(
                $user,
                $reply,
                $sessionId ?: ($pending['session_id'] ?? null),
                $ok ? ($cached['navigation'] ?? null) : null,
                $ok ? ($cached['result'] ?? null) : null
            );
        }

        if ($status === 'processing' && ! $claimed['claimed']) {
            return $this->confirmResponse(
                $user,
                'العملية بتنتنفذ دلوقتي. استنى لحظة ومتضغطش تاني.',
                $sessionId ?: ($pending['session_id'] ?? null),
                null,
                null
            );
        }

        $linkedSessionId = $sessionId ?: ($pending['session_id'] ?? null);

        if (! $confirmed) {
            $this->pendingActions->markCancelled($user->id, $pendingActionId);
            Log::channel('assistant')->info('assistant_action_cancelled', [
                'user_id' => $user->id,
                'pending_action_id' => $pendingActionId,
                'type' => $pending['type'] ?? null,
                'session_id' => $linkedSessionId,
            ]);

            $this->appendToSession($user->id, $linkedSessionId, 'تم إلغاء العملية.', [
                'last_action_result' => [
                    'ok' => false,
                    'cancelled' => true,
                    'type' => $pending['type'] ?? null,
                ],
            ]);

            return $this->confirmResponse(
                $user,
                'تم إلغاء العملية.',
                $linkedSessionId,
                null,
                null
            );
        }

        $executed = $this->tools->confirm($pending, $user);
        $ok = (bool) ($executed['ok'] ?? false);

        if (! $ok) {
            $error = (string) ($executed['error'] ?? 'فشل التنفيذ.');
            $this->pendingActions->markExecuted($user->id, $pendingActionId, [
                'error' => $error,
            ], false);
            $this->logFail('confirm_execution_failed', $user, $linkedSessionId, [
                'pending_action_id' => $pendingActionId,
                'type' => $pending['type'] ?? null,
                'error' => $error,
            ]);

            $this->appendToSession($user->id, $linkedSessionId, $error, [
                'last_action_result' => [
                    'ok' => false,
                    'type' => $pending['type'] ?? null,
                    'error' => $error,
                ],
            ]);

            return $this->confirmResponse($user, $error, $linkedSessionId, null, null);
        }

        // Successful confirmed mutation counts as one assistant message.
        $this->sessions->consumeMonthlyMessage($user->id);

        $type = (string) ($pending['type'] ?? '');
        $reply = $this->successReplyForType($type);
        $resultPayload = [
            'reply' => $reply,
            'result' => $executed['result'] ?? null,
            'navigation' => $executed['navigation'] ?? null,
        ];
        $this->pendingActions->markExecuted($user->id, $pendingActionId, $resultPayload, true);

        $contextPatch = [
            'draft' => null,
            'last_action_result' => [
                'ok' => true,
                'type' => $type,
                'result' => $executed['result'] ?? null,
            ],
        ];
        if (! empty($executed['result']['id']) && $type === 'create_customer') {
            $contextPatch['selected_customer'] = [
                'id' => $executed['result']['id'],
                'name' => $executed['result']['name'] ?? null,
                'phone' => null,
            ];
        }
        if (! empty($executed['result']['customer_id'])) {
            $contextPatch['selected_customer'] = [
                'id' => $executed['result']['customer_id'],
                'name' => $executed['result']['customer_name'] ?? null,
                'phone' => null,
            ];
        }

        $this->appendToSession($user->id, $linkedSessionId, $reply, $contextPatch);

        return $this->confirmResponse(
            $user,
            $reply,
            $linkedSessionId,
            $executed['navigation'] ?? null,
            $executed['result'] ?? null
        );
    }

    /**
     * @return array{enabled: bool, configured: bool, feature_enabled: bool, remaining_today: int, daily_limit: int}
     */
    public function status(User $user): array
    {
        $featureEnabled = $this->sessions->isFeatureEnabled($user->id);
        $limit = $this->sessions->monthlyLimit($user->id);
        $remaining = $featureEnabled ? $this->sessions->remainingMonthlyMessages($user->id) : 0;

        return [
            'enabled' => (bool) config('assistant.enabled', true) && $featureEnabled,
            'configured' => (string) config('assistant.gemini.api_key') !== '',
            'feature_enabled' => $featureEnabled,
            'remaining_today' => $remaining === PHP_INT_MAX ? 999999 : $remaining,
            'daily_limit' => $limit === PHP_INT_MAX ? 999999 : $limit,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function systemPrompt(array $context): string
    {
        $tz = (string) ($context['timezone'] ?? config('app.timezone') ?: 'UTC');
        $today = (string) ($context['today'] ?? now($tz)->toDateString());
        $memory = $this->sessions->contextPromptBlock($context);

        return <<<PROMPT
أنت مساعد تاجر لتطبيق أقساط (أقساطي). تكلّم باللهجة المصرية الطبيعية المختصرة، وافهم الفصحى والمصري مع بعض.
اليوم بالتاريخ الميلادي: {$today}
المنطقة الزمنية المعتمدة للحساب: {$tz}
متخمنش تواريخ بنفسك — السيرفر بيحسبها بـ Carbon (subMonthsNoOverflow).

## ذاكرة الجلسة المنظمة (أهم من آخر رسائل الشات)
{$memory}

## تقدر تساعد في
- عرض/بحث العملاء وملخص أقساط عميل.
- فتح شاشة أو تفاصيل عميل/قسط.
- إضافة عميل، إنشاء قسط، سداد قسط، أو عميل+قسط معاً.

## فهم المصري (أمثلة)
- «ورّيني العملاء» / «مين عندي» => list_customers
- «دور على محمد» / «في عميل اسمه...» => search_customers
- «لخّص أقساط فلان» / «حساب فلان إيه» => summarize_customer_installments
- «ضيف قسط» / «اعمل تقسيطة» / «قسط جديد» => اجمع البيانات خطوة بخطوة ثم propose_create_installment
- «سدد لـ...» / «حط دفعة» => propose_pay_installment_item بعد التوضيح
- «لا» / «عدّل المبلغ لـ...» / «الاسم غلط، خليه...» => عدّل المسودة فقط ومتبدأش الأسئلة من الأول

## إنشاء قسط بشكل تفاعلي
اسأل سؤال واحد في كل رد بهذا الترتيب إن ناقص:
1) العميل 2) اسم القسط 3) المبلغ 4) عدد الأشهر 5) شهور مقدمة (اختياري) 6) تاريخ البدء 7) ملاحظات (اختياري)
بعد اكتمال المطلوب: لخّص ثم استدعِ propose_* مرة واحدة فقط.
لو المستخدم قال كل التفاصيل مرة واحدة، كمّل الناقص فقط.

## قواعد صارمة
- استخدم الأدوات؛ متخترعش بيانات أو نجاح عملية.
- لو الأداة رجّعت ok=false أو error، قول للمستخدم إن التنفيذ فشل/ناقص — ممنوع تقول «تم» أو «اتنفذ».
- لو status=needs_input اسأل عن الناقص فقط.
- لو status=ambiguous أو أكتر من عميل، اعرض الاختيارات واسأل بالـ id.
- عمليات الإضافة/السداد تمر عبر propose_* فقط، ومتتنفذش غير بعد تأكيد صريح في التطبيق.
- تاريخ البداية: لو قال «من N شهر/شهور/قبل شهرين» استخدم start_months_ago=N (مش سنين). الحساب على السيرفر من {$today} في {$tz}.
- شهور ≠ سنين.
- الردود قصيرة بالعربي المصري فقط (بدون إنجليزي)، سؤال واحد قدر الإمكان.
- احتفظ بالعميل المختار والمسودة؛ لو المستخدم صحّح حقل، حدّثه ومتعيدش الباقي.
- المحافظة بالعربي أو الإنجليزي؛ الأداة هتظبطها.
- prepaid_months أرقام تبدأ من 1 (أول شهرين = [1,2]).
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
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>
     */
    private function contextPatchFromTool(string $name, array $args, array $toolResult): array
    {
        $patch = [];
        $result = is_array($toolResult['result'] ?? null) ? $toolResult['result'] : [];

        if (in_array($name, ['search_customers', 'list_customers'], true) && ($toolResult['ok'] ?? false)) {
            $customers = is_array($result['customers'] ?? null) ? $result['customers'] : [];
            $patch['last_search'] = [
                'query' => $args['query'] ?? null,
                'count' => $result['count'] ?? count($customers),
                'customers' => $customers,
            ];
            if (count($customers) === 1 && is_array($customers[0] ?? null)) {
                $patch['selected_customer'] = [
                    'id' => $customers[0]['id'] ?? null,
                    'name' => $customers[0]['name'] ?? null,
                    'phone' => $customers[0]['phone'] ?? null,
                ];
            }
        }

        if ($name === 'get_customer' && ($toolResult['ok'] ?? false)) {
            $patch['selected_customer'] = [
                'id' => $result['id'] ?? null,
                'name' => $result['name'] ?? null,
                'phone' => $result['phone'] ?? null,
            ];
        }

        if ($name === 'summarize_customer_installments' && ($toolResult['ok'] ?? false)) {
            $customer = is_array($result['customer'] ?? null) ? $result['customer'] : [];
            if ($customer !== []) {
                $patch['selected_customer'] = [
                    'id' => $customer['id'] ?? null,
                    'name' => $customer['name'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                ];
            }
            $patch['last_summary'] = [
                'customer_id' => $customer['id'] ?? null,
                'plans_count' => $result['plans_count'] ?? null,
                'total_amount' => $result['total_amount'] ?? null,
                'total_paid' => $result['total_paid'] ?? null,
                'total_remaining' => $result['total_remaining'] ?? null,
                'overdue_items_count' => $result['overdue_items_count'] ?? null,
            ];
        }

        if (str_starts_with($name, 'propose_') && ($toolResult['ok'] ?? false) && empty($result['ambiguous'])) {
            $intent = match ($name) {
                'propose_create_customer' => 'create_customer',
                'propose_create_installment' => 'create_installment',
                'propose_create_customer_with_installment' => 'create_customer_with_installment',
                'propose_pay_installment_item' => 'pay_installment_item',
                default => $name,
            };
            $fields = $args;
            if (! empty($toolResult['pending_action']['payload_preview']) && is_array($toolResult['pending_action']['payload_preview'])) {
                $fields = array_merge($fields, $toolResult['pending_action']['payload_preview']);
            }
            $patch['draft'] = [
                'intent' => $intent,
                'fields' => $fields,
            ];
        }

        if (($result['status'] ?? null) === 'ambiguous' || ! empty($result['ambiguous'])) {
            $customers = is_array($result['customers'] ?? null) ? $result['customers'] : [];
            $patch['last_search'] = [
                'count' => count($customers),
                'customers' => $customers,
                'ambiguous' => true,
            ];
        }

        if (($result['status'] ?? null) === 'needs_input') {
            $patch['draft'] = [
                'intent' => $name,
                'fields' => array_merge($args, [
                    'missing' => $result['missing'] ?? [],
                ]),
            ];
        }

        return $patch;
    }

    private function looksLikeFalseSuccess(string $reply): bool
    {
        $normalized = mb_strtolower(trim($reply));

        return in_array($normalized, ['تم.', 'تم', 'تم بنجاح', 'تم التنفيذ', 'done'], true)
            || str_contains($normalized, 'تم بنجاح')
            || str_contains($normalized, 'اتنفذ');
    }

    private function successReplyForType(string $type): string
    {
        return match ($type) {
            'create_customer' => 'تم إضافة العميل بنجاح.',
            'create_installment' => 'تم إنشاء القسط بنجاح.',
            'create_customer_with_installment' => 'تم إضافة العميل وإنشاء القسط بنجاح.',
            'pay_installment_item' => 'تم تسجيل الدفعة بنجاح.',
            default => 'تم التنفيذ بنجاح.',
        };
    }

    /**
     * @param  array<string, mixed>  $contextPatch
     */
    private function appendToSession(int $userId, ?string $sessionId, string $reply, array $contextPatch = []): void
    {
        if (! $sessionId) {
            return;
        }

        $session = $this->sessions->load($userId, $sessionId);
        $turns = $session['turns'];
        $turns[] = ['role' => 'assistant', 'text' => $reply];
        $context = $this->sessions->mergeContext($session['context'], $contextPatch);
        $this->sessions->save($userId, $sessionId, $turns, $context);
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
     * @param  array<string, mixed>|null  $navigation
     * @param  array<string, mixed>|null  $result
     * @return array{
     *   reply: string,
     *   session_id: string|null,
     *   pending_action: null,
     *   navigation: array<string, mixed>|null,
     *   result: array<string, mixed>|null,
     *   remaining_today: int
     * }
     */
    private function confirmResponse(
        User $user,
        string $reply,
        ?string $sessionId,
        ?array $navigation,
        ?array $result
    ): array {
        return [
            'reply' => $reply,
            'session_id' => $sessionId,
            'pending_action' => null,
            'navigation' => $navigation,
            'result' => $result,
            'remaining_today' => $this->sessions->remainingMonthlyMessages($user->id),
        ];
    }

    private function isBillableReadTool(string $name): bool
    {
        return in_array($name, [
            'search_customers',
            'list_customers',
            'get_customer',
            'summarize_customer_installments',
            'get_installment',
            'find_open_installment_item',
            'open_screen',
        ], true);
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
