<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Complete Meta's webhook ownership challenge.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $verifyToken = config('services.whatsapp.verify_token');

        if (
            $mode !== 'subscribe'
            || ! is_string($token)
            || ! is_string($verifyToken)
            || $verifyToken === ''
            || ! hash_equals($verifyToken, $token)
            || ! is_string($challenge)
        ) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Accept delivery and inbound-message events from Meta.
     *
     * Events are authenticated with the app secret before being acknowledged.
     */
    public function receive(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Rejected WhatsApp webhook with invalid signature');

            return response('Forbidden', 403);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    Log::info('Received WhatsApp message', [
                        'message_id' => $message['id'] ?? null,
                        'from' => $message['from'] ?? null,
                        'type' => $message['type'] ?? null,
                    ]);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    Log::info('WhatsApp message status update', [
                        'message_id' => $status['id'] ?? null,
                        'status' => $status['status'] ?? null,
                        'recipient_id' => $status['recipient_id'] ?? null,
                    ]);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function hasValidSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($appSecret) || $appSecret === '' || ! is_string($signature)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }
}
