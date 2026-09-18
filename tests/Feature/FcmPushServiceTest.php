<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\FcmPushService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('does not call FCM when FCM_ENABLED is false', function () {
    config(['services.fcm.enabled' => false]);
    Http::fake();

    $user = merchantWithPlan();
    DeviceToken::query()->create([
        'token' => 'disabled-token',
        'platform' => 'android',
        'user_id' => $user->id,
    ]);

    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'payment_due',
        'title' => 'دفعة مستحقة قريباً',
        'message' => 'تذكير بالدفعة',
        'data' => ['installment_id' => 9],
    ]);

    $sent = app(FcmPushService::class)->sendForNotification($notification);

    expect($sent)->toBe(0);
    Http::assertNothingSent();
});

it('posts an FCM HTTP v1 message for each vendor device token', function () {
    config([
        'services.fcm.enabled' => true,
        'services.fcm.project_id' => 'aqsaty-test',
        'services.fcm.credentials' => base_path('tests/fixtures/firebase-service-account.json'),
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.test',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'https://fcm.googleapis.com/v1/projects/aqsaty-test/messages:send' => Http::response([
            'name' => 'projects/aqsaty-test/messages/1',
        ]),
    ]);

    $user = merchantWithPlan();
    DeviceToken::query()->create([
        'token' => 'live-token',
        'platform' => 'android',
        'user_id' => $user->id,
    ]);

    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'payment_due',
        'title' => 'دفعة مستحقة قريباً',
        'message' => 'دفعة بقيمة 100.00 ج.م',
        'data' => ['installment_id' => 9, 'item_id' => 3],
    ]);

    $sent = app(FcmPushService::class)->sendForNotification($notification);

    expect($sent)->toBe(1);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'messages:send')) {
            return false;
        }

        $body = $request->data();

        return $body['message']['token'] === 'live-token'
            && $body['message']['notification']['title'] === 'دفعة مستحقة قريباً'
            && $body['message']['data']['type'] === 'payment_due'
            && $body['message']['data']['installment_id'] === '9'
            && $body['message']['android']['priority'] === 'high';
    });
});

it('deletes unregistered FCM tokens', function () {
    config([
        'services.fcm.enabled' => true,
        'services.fcm.project_id' => 'aqsaty-test',
        'services.fcm.credentials' => base_path('tests/fixtures/firebase-service-account.json'),
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.test',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'https://fcm.googleapis.com/v1/projects/aqsaty-test/messages:send' => Http::response([
            'error' => [
                'status' => 'NOT_FOUND',
                'details' => [
                    ['errorCode' => 'UNREGISTERED'],
                ],
            ],
        ], 404),
    ]);

    $user = merchantWithPlan();
    DeviceToken::query()->create([
        'token' => 'stale-token',
        'platform' => 'android',
        'user_id' => $user->id,
    ]);

    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'payment_due',
        'title' => 'عنوان',
        'message' => 'نص',
        'data' => [],
    ]);

    app(FcmPushService::class)->sendForNotification($notification);

    expect(DeviceToken::query()->where('token', 'stale-token')->exists())->toBeFalse();
});

it('throws for retryable FCM server errors', function () {
    config([
        'services.fcm.enabled' => true,
        'services.fcm.project_id' => 'aqsaty-test',
        'services.fcm.credentials' => base_path('tests/fixtures/firebase-service-account.json'),
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.test',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'https://fcm.googleapis.com/v1/projects/aqsaty-test/messages:send' => Http::response([
            'error' => [
                'status' => 'UNAVAILABLE',
                'message' => 'Service unavailable',
            ],
        ], 503),
    ]);

    $user = merchantWithPlan();
    DeviceToken::query()->create([
        'token' => 'retryable-token',
        'platform' => 'android',
        'user_id' => $user->id,
    ]);

    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'payment_due',
        'title' => 'عنوان',
        'message' => 'نص',
        'data' => [],
    ]);

    expect(fn () => app(FcmPushService::class)->sendForNotification($notification))
        ->toThrow(\RuntimeException::class);

    expect(DeviceToken::query()->where('token', 'retryable-token')->exists())->toBeTrue();
});

it('queues a push job after creating an in-app notification', function () {
    Queue::fake();

    $user = merchantWithPlan();

    app(NotificationService::class)->create(
        $user,
        'payment_received',
        'تم استلام دفعة',
        'تم استلام دفعة جديدة',
        ['item_id' => 42],
        false
    );

    Queue::assertPushed(SendPushNotificationJob::class);
});
