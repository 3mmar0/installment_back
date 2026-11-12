<?php

use App\Helpers\LimitsHelper;
use App\Models\MerchantGift;
use App\Models\Notification;
use App\Models\UserLimit;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Support\Facades\Http;

it('blocks assistant when the feature flag is off', function () {
    config([
        'assistant.enabled' => true,
        'assistant.gemini.api_key' => 'test-key',
    ]);

    $merchant = actingAsMerchant([
        'features' => [
            'basic_reports' => true,
            'assistant' => false,
            'whatsapp' => false,
        ],
    ]);

    expect(app(\App\Services\Assistant\AssistantSessionStore::class)->isFeatureEnabled($merchant->id))
        ->toBeFalse();

    $this->postJson('/api/assistant/message', ['message' => 'مساعدة'])
        ->assertOk()
        ->assertJsonPath('data.reply', 'المساعد الذكي غير متاح في خطتك الحالية.');
});

it('lets admin gifts raise the assistant monthly allowance', function () {
    $merchant = merchantWithPlan([
        'assistant_messages' => ['from' => 0, 'to' => 1],
        'assistant_messages_used' => 1,
        'assistant_messages_bonus' => 0,
        'usage_period' => now()->format('Y-m'),
    ]);

    expect(LimitsHelper::canCreate($merchant->id, 'assistant_messages'))->toBeFalse();

    $admin = actingAsPlatformAdmin();
    LimitsHelper::grantGift($merchant->id, 'assistant_messages', 2, $admin->id, 'هدية تجربة');

    expect(LimitsHelper::canCreate($merchant->id, 'assistant_messages'))->toBeTrue()
        ->and(MerchantGift::query()->where('user_id', $merchant->id)->count())->toBe(1)
        ->and((int) UserLimit::query()->where('user_id', $merchant->id)->value('assistant_messages_bonus'))->toBe(2);

    $giftNotification = Notification::query()
        ->where('user_id', $merchant->id)
        ->where('type', 'merchant_gift')
        ->latest('id')
        ->first();

    expect($giftNotification)->not->toBeNull()
        ->and($giftNotification?->data['display_as'] ?? null)->toBe('modal')
        ->and((int) ($giftNotification?->data['amount'] ?? 0))->toBe(2)
        ->and($giftNotification?->data['resource'] ?? null)->toBe('assistant_messages');
});

it('resets monthly messaging usage while keeping gifts by default', function () {
    $merchant = merchantWithPlan([
        'assistant_messages' => ['from' => 0, 'to' => 5],
        'assistant_messages_used' => 5,
        'assistant_messages_bonus' => 3,
        'whatsapp_messages_used' => 4,
        'usage_period' => now()->format('Y-m'),
    ]);

    actingAsPlatformAdmin();

    $this->postJson("/api/users/{$merchant->id}/limits/reset-usage")
        ->assertOk()
        ->assertJsonPath('data.usage.assistant_messages_used', 0)
        ->assertJsonPath('data.usage.whatsapp_messages_used', 0)
        ->assertJsonPath('data.usage.assistant_messages_bonus', 3);
});

it('meters whatsapp sends against merchant limits', function () {
    config()->set('services.whatsapp', [
        'enabled' => true,
        'phone_number_id' => '123',
        'access_token' => 'token',
        'graph_version' => 'v21.0',
        'app_secret' => null,
        'verify_token' => null,
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
    ]);

    $merchant = merchantWithPlan([
        'whatsapp_messages' => ['from' => 0, 'to' => 1],
        'whatsapp_messages_used' => 0,
        'whatsapp_messages_bonus' => 0,
        'usage_period' => now()->format('Y-m'),
        'features' => [
            'assistant' => true,
            'whatsapp' => true,
            'basic_reports' => true,
        ],
    ]);

    app(WhatsAppCloudApiService::class)->sendText('201000000000', 'hi', $merchant);

    expect((int) $merchant->fresh()->userLimit->whatsapp_messages_used)->toBe(1);

    expect(fn () => app(WhatsAppCloudApiService::class)->sendText('201000000000', 'again', $merchant->fresh()))
        ->toThrow(RuntimeException::class);
});
