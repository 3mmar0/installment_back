<?php

test('the application redirects guests to documentation', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
    $response->assertRedirect('/documentation');
});
