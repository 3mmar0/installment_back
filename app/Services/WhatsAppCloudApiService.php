<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudApiService
{
    /**
     * Send a free-form message inside WhatsApp's 24-hour customer-service window.
     * Use a pre-approved template for business-initiated messages outside that window.
     *
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body): array
    {
        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizeRecipient($to),
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    /**
     * Send an approved template. Components follow Meta's Cloud API template format.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public function sendTemplate(string $to, string $name, string $language = 'ar', array $components = []): array
    {
        $template = [
            'name' => $name,
            'language' => ['code' => $language],
        ];

        if ($components !== []) {
            $template['components'] = $components;
        }

        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizeRecipient($to),
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload): array
    {
        if (! config('services.whatsapp.enabled')) {
            throw new RuntimeException('WhatsApp Cloud API is disabled. Set WHATSAPP_ENABLED=true after configuration.');
        }

        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');
        $version = config('services.whatsapp.graph_version');

        if (! is_string($phoneNumberId) || $phoneNumberId === '' || ! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('WhatsApp Cloud API credentials are incomplete.');
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to reach the WhatsApp Cloud API.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp Cloud API request failed: '.$response->body());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }

    private function normalizeRecipient(string $recipient): string
    {
        return preg_replace('/\D+/', '', $recipient) ?: $recipient;
    }
}
