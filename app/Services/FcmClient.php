<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmClient
{
    public function send(string $token, string $title, string $body, array $data): array
    {
        $projectId = (string) config('services.fcm.project_id');

        if ($projectId === '') {
            throw new RuntimeException('FIREBASE_PROJECT_ID is not configured.');
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'default',
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                $payload
            );

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'json' => $response->json() ?? [],
        ];
    }

    private function accessToken(): string
    {
        $cached = cache()->get('fcm_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();
        $now = time();
        $jwt = $this->encodeJwt([
            'iss' => $credentials['client_email'],
            'sub' => $credentials['client_email'],
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ], $credentials['private_key']);

        $response = Http::asForm()->post(
            $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]
        );

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('Failed to obtain FCM access token.');
        }

        $token = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        cache()->put('fcm_access_token', $token, max(60, $expiresIn - 60));

        return $token;
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri?: string}
     */
    private function credentials(): array
    {
        $path = (string) config('services.fcm.credentials');
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('Firebase service-account file is missing.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            throw new RuntimeException('Firebase service-account file is invalid.');
        }

        return $decoded;
    }

    private function encodeJwt(array $claims, string $privateKey): string
    {
        $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->b64(json_encode($claims));
        $unsigned = $header.'.'.$payload;

        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('Failed to sign FCM JWT.');
        }

        return $unsigned.'.'.$this->b64($signature);
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
