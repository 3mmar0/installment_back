<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmPushService
{
    public function __construct(private readonly FcmClient $client) {}

    public function sendForNotification(Notification $notification): int
    {
        if (! config('services.fcm.enabled')) {
            return 0;
        }

        $tokens = DeviceToken::query()
            ->when(
                $notification->user_id,
                fn ($q) => $q->where('user_id', $notification->user_id)
            )
            ->when(
                $notification->client_account_id,
                fn ($q) => $q->where('client_account_id', $notification->client_account_id)
            )
            ->when(
                ! $notification->user_id && ! $notification->client_account_id,
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->get();

        $sent = 0;

        foreach ($tokens as $device) {
            try {
                $result = $this->client->send(
                    $device->token,
                    $notification->title,
                    $notification->message,
                    $this->stringData($notification)
                );
            } catch (\Throwable $e) {
                Log::warning('FCM send failed', [
                    'notification_id' => $notification->id,
                    'token_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($this->isUnregistered($result)) {
                $device->delete();

                continue;
            }

            if ($result['ok']) {
                $device->forceFill(['last_used_at' => now()])->save();
                $sent++;

                continue;
            }

            $this->handleFailedResult($notification, $device, $result);
        }

        return $sent;
    }

    /**
     * @return array<string, string>
     */
    private function stringData(Notification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $flat = [
            'notification_id' => (string) $notification->id,
            'type' => (string) $notification->type,
        ];

        foreach (['installment_id', 'item_id', 'customer_id'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $flat[$key] = (string) $data[$key];
            }
        }

        return $flat;
    }

    private function isUnregistered(array $result): bool
    {
        if ($result['ok']) {
            return false;
        }

        $json = $result['json'] ?? [];
        $status = $json['error']['status'] ?? '';
        $code = $json['error']['details'][0]['errorCode'] ?? '';

        return in_array($status, ['NOT_FOUND', 'UNREGISTERED'], true)
            || $code === 'UNREGISTERED';
    }

    private function handleFailedResult(Notification $notification, DeviceToken $device, array $result): void
    {
        $status = (int) ($result['status'] ?? 0);
        $errorStatus = $result['json']['error']['status'] ?? null;

        Log::warning('FCM send returned non-success response', [
            'notification_id' => $notification->id,
            'token_id' => $device->id,
            'status' => $status,
            'error_status' => $errorStatus,
        ]);

        if (in_array($status, [401, 403], true)) {
            cache()->forget('fcm_access_token');
        }

        if ($status === 429 || $status >= 500) {
            throw new RuntimeException("Retryable FCM response: {$status}");
        }
    }
}
