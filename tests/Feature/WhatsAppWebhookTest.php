<?php

use Illuminate\Support\Facades\Http;

it('answers Meta webhook verification with the supplied challenge', function () {
    config()->set('services.whatsapp.verify_token', 'test-verify-token');

    $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=challenge-123')
        ->assertOk()
        ->assertSeeText('challenge-123');
});

it('rejects a webhook verification request with an incorrect token', function () {
    config()->set('services.whatsapp.verify_token', 'test-verify-token');

    $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
        ->assertForbidden();
});

it('accepts a signed WhatsApp delivery event', function () {
    config()->set('services.whatsapp.app_secret', 'test-app-secret');
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.test',
                        'status' => 'delivered',
                        'recipient_id' => '201234567890',
                    ]],
                ],
            ]],
        ]],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        '/api/webhooks/whatsapp',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'test-app-secret')],
        $raw,
    )->assertOk()->assertSeeText('EVENT_RECEIVED');
});

it('sends WhatsApp text through the configured Cloud API', function () {
    config()->set('services.whatsapp', [
        'enabled' => true,
        'graph_version' => 'v24.0',
        'phone_number_id' => '12345',
        'access_token' => 'test-token',
    ]);
    Http::fake(['https://graph.facebook.com/v24.0/12345/messages' => Http::response([
        'messages' => [['id' => 'wamid.sent']],
    ])]);

    $result = app(App\Services\WhatsAppCloudApiService::class)->sendText('+20 123 456 7890', 'Hello');

    expect($result['messages'][0]['id'])->toBe('wamid.sent');
    Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v24.0/12345/messages'
        && $request['to'] === '201234567890'
        && $request['text']['body'] === 'Hello');
});
