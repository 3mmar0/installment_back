<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiClient
{
    /**
     * Text/Flash-Lite models suitable for chat + tool calling.
     * Live / TTS / image / audio models are intentionally excluded.
     *
     * @var list<string>
     */
    public const DEFAULT_FALLBACK_MODELS = [
        'gemini-3.8-flash',
        'gemini-3.5-flash-lite',
        'gemini-3.6-flash',
        'gemini-flash-latest',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function generateContent(array $contents, array $tools = [], ?string $systemInstruction = null): array
    {
        $apiKey = (string) config('assistant.gemini.api_key');
        if ($apiKey === '') {
            $this->logError('GEMINI_API_KEY missing', []);
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => new \stdClass,
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        if ($tools !== []) {
            $payload['tools'] = [
                ['functionDeclarations' => $tools],
            ];
        }

        $models = $this->models();
        $attempts = [];
        $lastError = 'Gemini request failed';
        $maxRetries = max(0, (int) config('assistant.gemini.retries', 1));

        foreach ($models as $index => $model) {
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                try {
                    $response = $this->request($apiKey, $model, $payload);
                } catch (Throwable $e) {
                    $safeError = $this->redactSecrets($e->getMessage());
                    $attempts[] = [
                        'model' => $model,
                        'status' => null,
                        'retry' => $retry,
                        'error' => $safeError,
                    ];
                    $this->logWarning('Gemini HTTP/transport failure', [
                        'model' => $model,
                        'attempt' => $index + 1,
                        'retry' => $retry,
                        'total_models' => count($models),
                        'error' => $safeError,
                    ]);
                    $lastError = $safeError;

                    // SSL / CA failures will hit every model the same way — fail fast.
                    if ($this->isSslError($safeError)) {
                        throw new RuntimeException('Gemini request failed: '.$safeError);
                    }

                    if ($retry < $maxRetries && $this->isTransientTransportError($safeError)) {
                        usleep(250000 * ($retry + 1));
                        continue;
                    }

                    break;
                }

                if ($response->successful()) {
                    if ($attempts !== []) {
                        $this->logInfo('Gemini succeeded after fallbacks/retries', [
                            'model' => $model,
                            'failed_attempts' => $attempts,
                        ]);
                    }

                    /** @var array<string, mixed> $json */
                    $json = $response->json() ?? [];

                    return $json;
                }

                $message = data_get($response->json(), 'error.message')
                    ?: data_get($response->json(), 'error.status')
                    ?: 'HTTP '.$response->status();
                $message = $this->redactSecrets((string) $message);
                $lastError = $message;
                $attempt = [
                    'model' => $model,
                    'status' => $response->status(),
                    'retry' => $retry,
                    'error' => $message,
                ];
                $attempts[] = $attempt;

                if ($retry < $maxRetries && $this->shouldRetrySameModel($response->status(), $message)) {
                    $this->logWarning('Gemini transient failure, retrying same model', $attempt);
                    usleep(300000 * ($retry + 1));
                    continue;
                }

                        if ($this->shouldTryNextModel($response->status(), $message)) {
                    $this->logWarning('Gemini model failed, trying next fallback', [
                        ...$attempt,
                        'attempt' => $index + 1,
                        'total_models' => count($models),
                    ]);
                    break;
                }

                // Thought-signature / tool-protocol mismatches: try a simpler Flash model next.
                if (str_contains(strtolower($message), 'thought_signature')) {
                    $this->logWarning('Gemini thought_signature failure, trying next fallback', [
                        ...$attempt,
                        'attempt' => $index + 1,
                        'total_models' => count($models),
                    ]);
                    break;
                }

                $this->logError('Gemini hard failure (no more fallbacks for this error)', [
                    ...$attempt,
                    'attempts' => $attempts,
                ]);

                throw new RuntimeException('Gemini request failed: '.$message);
            }
        }

        $this->logError('Gemini exhausted all models', [
            'models' => $models,
            'attempts' => $attempts,
            'last_error' => $lastError,
        ]);

        throw new RuntimeException('Gemini request failed: '.$lastError);
    }

    /**
     * @return list<string>
     */
    private function models(): array
    {
        $primary = (string) config('assistant.gemini.model', 'gemini-3.8-flash');
        $fallbacks = config('assistant.gemini.fallback_models', self::DEFAULT_FALLBACK_MODELS);
        if (! is_array($fallbacks) || $fallbacks === []) {
            $fallbacks = self::DEFAULT_FALLBACK_MODELS;
        }

        $models = [$primary];
        foreach ($fallbacks as $fallback) {
            $name = trim((string) $fallback);
            if ($name !== '' && ! in_array($name, $models, true)) {
                $models[] = $name;
            }
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $apiKey, string $model, array $payload): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) config('assistant.gemini.base_url'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent";

        $request = Http::timeout((int) config('assistant.gemini.timeout', 30))
            ->acceptJson()
            ->asJson();

        if ($this->shouldDisableSslVerify()) {
            $request = $request->withoutVerifying();
        }

        return $request->post($url.'?key='.urlencode($apiKey), $payload);
    }

    private function shouldDisableSslVerify(): bool
    {
        // Never disable TLS verification in production.
        if (app()->environment('production')) {
            return false;
        }

        $configured = config('assistant.gemini.http_verify');
        if ($configured !== null && filter_var($configured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false) {
            return true;
        }

        // Local Windows PHP builds frequently ship without a CA bundle.
        if (PHP_OS_FAMILY === 'Windows') {
            $ca = (string) (ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: '');

            return $ca === '' || ! is_file($ca);
        }

        return false;
    }

    private function shouldTryNextModel(int $status, string $message): bool
    {
        if (in_array($status, [404, 429, 503], true)) {
            return true;
        }

        $haystack = strtolower($message);

        return str_contains($haystack, 'resource_exhausted')
            || str_contains($haystack, 'rate limit')
            || str_contains($haystack, 'quota')
            || str_contains($haystack, 'exceeded')
            || str_contains($haystack, 'not found')
            || str_contains($haystack, 'not supported')
            || str_contains($haystack, 'unavailable')
            || str_contains($haystack, 'ssl certificate')
            || str_contains($haystack, 'curl error 60')
            || str_contains($haystack, 'thought_signature');
    }

    private function shouldRetrySameModel(int $status, string $message): bool
    {
        if (in_array($status, [429, 500, 502, 503, 504], true)) {
            return true;
        }

        $haystack = strtolower($message);

        return str_contains($haystack, 'resource_exhausted')
            || str_contains($haystack, 'rate limit')
            || str_contains($haystack, 'unavailable')
            || str_contains($haystack, 'timeout')
            || str_contains($haystack, 'temporarily');
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
        $redacted = preg_replace('/([?&]key=)[^&\s]+/i', '$1[REDACTED]', $value) ?? $value;
        $apiKey = (string) config('assistant.gemini.api_key');
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
