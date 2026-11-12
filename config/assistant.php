<?php

return [
    'enabled' => env('ASSISTANT_ENABLED', true),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        /**
         * Comma-separated fallbacks used on rate-limit / quota / unavailable.
         * Keep this short — long lists make every failure feel extremely slow.
         */
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'GEMINI_FALLBACK_MODELS',
                'gemini-3.5-flash-lite,gemini-3.6-flash,gemini-flash-latest'
            ))
        ))),
        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta'
        ),
        'timeout' => (int) env('GEMINI_TIMEOUT', 35),
        /** Extra attempts on the same model for transient 429/5xx before fallback. */
        'retries' => (int) env('GEMINI_RETRIES', 0),
        /**
         * Set GEMINI_HTTP_VERIFY=false only for local broken CA bundles.
         * Production always verifies TLS regardless of this flag.
         */
        'http_verify' => env('GEMINI_HTTP_VERIFY', true),
    ],

    /** Max assistant messages a vendor may send per calendar day. */
    'daily_message_limit' => (int) env('ASSISTANT_DAILY_MESSAGE_LIMIT', 30),

    /** How many prior turns to keep in the session window (interactive wizards need more). */
    'session_turn_limit' => (int) env('ASSISTANT_SESSION_TURN_LIMIT', 24),

    /** Pending confirmations expire after this many seconds. */
    'pending_action_ttl' => (int) env('ASSISTANT_PENDING_ACTION_TTL', 600),

    /** Keep executed/failed receipts for idempotent retries. */
    'pending_action_result_ttl' => (int) env('ASSISTANT_PENDING_ACTION_RESULT_TTL', 3600),

    /** Session conversation cache TTL in seconds. */
    'session_ttl' => (int) env('ASSISTANT_SESSION_TTL', 3600),
];
