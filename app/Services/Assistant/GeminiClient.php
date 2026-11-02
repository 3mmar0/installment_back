<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    /**
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function generateContent(array $contents, array $tools = [], ?string $systemInstruction = null): array
    {
        $apiKey = (string) config('assistant.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = (string) config('assistant.gemini.model', 'gemini-3.5-flash-lite');
        $baseUrl = rtrim((string) config('assistant.gemini.base_url'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent";

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
            ],
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

        $request = Http::timeout((int) config('assistant.gemini.timeout', 30))
            ->acceptJson()
            ->asJson();

        // Local Windows PHP installs often lack a CA bundle; keep verify on in production.
        if (app()->environment('local') && PHP_OS_FAMILY === 'Windows') {
            $request = $request->withoutVerifying();
        }

        $response = $request->post($url.'?key='.urlencode($apiKey), $payload);

        if (! $response->successful()) {
            $message = data_get($response->json(), 'error.message')
                ?: data_get($response->json(), 'error.status')
                ?: 'HTTP '.$response->status();

            throw new RuntimeException('Gemini request failed: '.$message);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }
}
