<?php

return [
    'enabled' => env('ASSISTANT_ENABLED', true),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta'
        ),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],

    /** Max assistant messages a vendor may send per calendar day. */
    'daily_message_limit' => (int) env('ASSISTANT_DAILY_MESSAGE_LIMIT', 30),

    /** How many prior turns to keep in the session window. */
    'session_turn_limit' => (int) env('ASSISTANT_SESSION_TURN_LIMIT', 10),

    /** Pending confirmations expire after this many seconds. */
    'pending_action_ttl' => (int) env('ASSISTANT_PENDING_ACTION_TTL', 600),

    /** Session conversation cache TTL in seconds. */
    'session_ttl' => (int) env('ASSISTANT_SESSION_TTL', 3600),
];
