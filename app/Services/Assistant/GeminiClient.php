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
        'gemini-3.1-flash-lite',
        'gemini-3.6-flash',
        'gemini-3.8-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.5-flash',
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

        foreach ($models as $index => $model) {
            try {
                $response = $this->request($apiKey, $model, $payload);
            } catch (Throwable $e) {
                $attempts[] = [
                    'model' => $model,
                    'status' => null,
                    'error' => $e->getMessage(),
                ];
                $this->logWarning('Gemini HTTP/transport failure, trying next model', [
                    'model' => $model,
                    'attempt' => $index + 1,
                    'total_models' => count($models),
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e->getMessage();
                continue;
            }

            if ($response->successful()) {
                if ($attempts !== []) {
                    $this->logInfo('Gemini succeeded after fallbacks', [
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
            $lastError = $message;
            $attempt = [
                'model' => $model,
                'status' => $response->status(),
                'error' => $message,
            ];
            $attempts[] = $attempt;

            if ($this->shouldTryNextModel($response->status(), $message)) {
                $this->logWarning('Gemini model failed, trying next fallback', [
                    ...$attempt,
                    'attempt' => $index + 1,
                    'total_models' => count($models),
                ]);
                continue;
            }

            $this->logError('Gemini hard failure (no more fallbacks for this error)', [
                ...$attempt,
                'attempts' => $attempts,
            ]);

            throw new RuntimeException('Gemini request failed: '.$message);
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
        $primary = (string) config('assistant.gemini.model', 'gemini-3.5-flash-lite');
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

        // Local Windows PHP installs often lack a CA bundle; keep verify on in production.
        if (app()->environment('local') && PHP_OS_FAMILY === 'Windows') {
            $request = $request->withoutVerifying();
        }

        return $request->post($url.'?key='.urlencode($apiKey), $payload);
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
            || str_contains($haystack, 'unavailable');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWarning(string $message, array $context): void
    {
        Log::channel('assistant')->warning($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context): void
    {
        Log::channel('assistant')->error($message, $context);
        Log::error('[assistant] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logInfo(string $message, array $context): void
    {
        Log::channel('assistant')->info($message, $context);
    }
}
