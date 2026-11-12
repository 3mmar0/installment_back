<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeepSeekClient implements AssistantLlmClient
{
    public function isConfigured(): bool
    {
        return (string) config('assistant.deepseek.api_key') !== '';
    }

    /**
     * {@inheritdoc}
     */
    public function complete(array $messages, array $tools, string $systemInstruction): array
    {
        $apiKey = (string) config('assistant.deepseek.api_key');
        if ($apiKey === '') {
            $this->logError('DEEPSEEK_API_KEY missing', []);
            throw new RuntimeException('DEEPSEEK_API_KEY is not configured.');
        }

        $payload = [
            'model' => (string) config('assistant.deepseek.model', 'deepseek-chat'),
            'messages' => $this->toOpenAiMessages($messages, $systemInstruction),
        ];

        $openAiTools = $this->toOpenAiTools($tools);
        if ($openAiTools !== []) {
            $payload['tools'] = $openAiTools;
            $payload['tool_choice'] = 'auto';
        }

        $maxRetries = max(0, (int) config('assistant.deepseek.retries', 0));
        $attempts = [];
        $lastError = 'DeepSeek request failed';

        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            try {
                $response = $this->request($apiKey, $payload);
            } catch (Throwable $e) {
                $safeError = $this->redactSecrets($e->getMessage());
                $attempts[] = [
                    'status' => null,
                    'retry' => $retry,
                    'error' => $safeError,
                ];
                $this->logWarning('DeepSeek HTTP/transport failure', [
                    'retry' => $retry,
                    'error' => $safeError,
                ]);
                $lastError = $safeError;

                if ($this->isSslError($safeError)) {
                    throw new RuntimeException('DeepSeek request failed: '.$safeError);
                }

                if ($retry < $maxRetries && $this->isTransientTransportError($safeError)) {
                    usleep(250000 * ($retry + 1));
                    continue;
                }

                throw new RuntimeException('DeepSeek request failed: '.$safeError);
            }

            if ($response->successful()) {
                if ($attempts !== []) {
                    $this->logInfo('DeepSeek succeeded after retries', [
                        'failed_attempts' => $attempts,
                    ]);
                }

                /** @var array<string, mixed> $json */
                $json = $response->json() ?? [];

                return $this->normalizeResponse($json);
            }

            $message = data_get($response->json(), 'error.message')
                ?: data_get($response->json(), 'error.type')
                ?: 'HTTP '.$response->status();
            $message = $this->redactSecrets((string) $message);
            $lastError = $message;
            $attempt = [
                'status' => $response->status(),
                'retry' => $retry,
                'error' => $message,
            ];
            $attempts[] = $attempt;

            if ($retry < $maxRetries && $this->shouldRetry($response->status(), $message)) {
                $this->logWarning('DeepSeek transient failure, retrying', $attempt);
                usleep(300000 * ($retry + 1));
                continue;
            }

            $this->logError('DeepSeek hard failure', [
                ...$attempt,
                'attempts' => $attempts,
            ]);

            throw new RuntimeException('DeepSeek request failed: '.$message);
        }

        $this->logError('DeepSeek exhausted retries', [
            'attempts' => $attempts,
            'last_error' => $lastError,
        ]);

        throw new RuntimeException('DeepSeek request failed: '.$lastError);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function toOpenAiMessages(array $messages, string $systemInstruction): array
    {
        $out = [];
        if (trim($systemInstruction) !== '') {
            $out[] = [
                'role' => 'system',
                'content' => $systemInstruction,
            ];
        }

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? '');
            if ($role === 'user') {
                $out[] = [
                    'role' => 'user',
                    'content' => (string) ($message['content'] ?? ''),
                ];
                continue;
            }

            if ($role === 'assistant') {
                $row = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? null,
                ];
                $toolCalls = $message['tool_calls'] ?? [];
                if (is_array($toolCalls) && $toolCalls !== []) {
                    $row['tool_calls'] = [];
                    foreach ($toolCalls as $call) {
                        if (! is_array($call)) {
                            continue;
                        }
                        $row['tool_calls'][] = [
                            'id' => (string) ($call['id'] ?? Str::uuid()),
                            'type' => 'function',
                            'function' => [
                                'name' => (string) ($call['name'] ?? ''),
                                'arguments' => json_encode(
                                    is_array($call['args'] ?? null) ? $call['args'] : new \stdClass,
                                    JSON_UNESCAPED_UNICODE
                                ),
                            ],
                        ];
                    }
                }
                $out[] = $row;
                continue;
            }

            if ($role === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($message['tool_call_id'] ?? ''),
                    'content' => is_string($message['content'] ?? null)
                        ? $message['content']
                        : json_encode($message['content'] ?? new \stdClass, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>
     */
    private function toOpenAiTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if (! is_array($tool) || ! isset($tool['name'])) {
                continue;
            }
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => (string) $tool['name'],
                    'description' => (string) ($tool['description'] ?? ''),
                    'parameters' => is_array($tool['parameters'] ?? null)
                        ? $tool['parameters']
                        : ['type' => 'object', 'properties' => new \stdClass],
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *   text: ?string,
     *   tool_calls: list<array{id: string, name: string, args: array<string, mixed>}>,
     *   provider_meta: array<string, mixed>
     * }
     */
    private function normalizeResponse(array $json): array
    {
        $message = data_get($json, 'choices.0.message');
        if (! is_array($message)) {
            return [
                'text' => null,
                'tool_calls' => [],
                'provider_meta' => [],
            ];
        }

        $text = $message['content'] ?? null;
        if (is_string($text)) {
            $text = trim($text);
            if ($text === '') {
                $text = null;
            }
        } else {
            $text = null;
        }

        $toolCalls = [];
        $rawCalls = $message['tool_calls'] ?? [];
        if (is_array($rawCalls)) {
            foreach ($rawCalls as $call) {
                if (! is_array($call)) {
                    continue;
                }
                $fn = $call['function'] ?? null;
                if (! is_array($fn) || ! isset($fn['name'])) {
                    continue;
                }
                $argsRaw = $fn['arguments'] ?? '{}';
                $args = [];
                if (is_string($argsRaw)) {
                    $decoded = json_decode($argsRaw, true);
                    $args = is_array($decoded) ? $decoded : [];
                } elseif (is_array($argsRaw)) {
                    $args = $argsRaw;
                }

                $toolCalls[] = [
                    'id' => (string) ($call['id'] ?? Str::uuid()),
                    'name' => (string) $fn['name'],
                    'args' => $args,
                ];
            }
        }

        return [
            'text' => $text,
            'tool_calls' => $toolCalls,
            'provider_meta' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $apiKey, array $payload): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) config('assistant.deepseek.base_url', 'https://api.deepseek.com'), '/');

        return Http::timeout((int) config('assistant.deepseek.timeout', 35))
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->post($baseUrl.'/chat/completions', $payload);
    }

    private function shouldRetry(int $status, string $message): bool
    {
        if (in_array($status, [429, 500, 502, 503, 504], true)) {
            return true;
        }

        $haystack = strtolower($message);

        return str_contains($haystack, 'rate limit')
            || str_contains($haystack, 'timeout')
            || str_contains($haystack, 'temporarily')
            || str_contains($haystack, 'unavailable');
    }

    private function isTransientTransportError(string $message): bool
    {
        $haystack = strtolower($message);

        return str_contains($haystack, 'timed out')
            || str_contains($haystack, 'timeout')
            || str_contains($haystack, 'connection reset')
            || str_contains($haystack, 'could not resolve host')
            || str_contains($haystack, 'failed to connect');
    }

    private function isSslError(string $message): bool
    {
        $haystack = strtolower($message);

        return str_contains($haystack, 'ssl certificate')
            || str_contains($haystack, 'curl error 60')
            || str_contains($haystack, 'unable to get local issuer certificate')
            || str_contains($haystack, 'certificate verify failed');
    }

    private function redactSecrets(string $value): string
    {
        $redacted = preg_replace('/(Bearer\s+)(\S+)/i', '$1[REDACTED]', $value) ?? $value;
        $apiKey = (string) config('assistant.deepseek.api_key');
        if ($apiKey !== '' && str_contains($redacted, $apiKey)) {
            $redacted = str_replace($apiKey, '[REDACTED]', $redacted);
        }

        return $redacted;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWarning(string $message, array $context): void
    {
        Log::channel('assistant')->warning($message, $this->redactContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context): void
    {
        $safe = $this->redactContext($context);
        Log::channel('assistant')->error($message, $safe);
        Log::error('[assistant] '.$message, $safe);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logInfo(string $message, array $context): void
    {
        Log::channel('assistant')->info($message, $this->redactContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redactContext(array $context): array
    {
        array_walk_recursive($context, function (&$value): void {
            if (is_string($value)) {
                $value = $this->redactSecrets($value);
            }
        });

        return $context;
    }
}
