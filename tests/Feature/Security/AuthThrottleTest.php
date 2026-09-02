<?php

it('throttles repeated failed login attempts', function () {
    $statuses = [];

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $statuses[] = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->getStatusCode();
    }

    expect($statuses)->toContain(429);
});

it('throttles repeated forgot-password requests', function () {
    $statuses = [];

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $statuses[] = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])->getStatusCode();
    }

    expect($statuses)->toContain(429);
});
