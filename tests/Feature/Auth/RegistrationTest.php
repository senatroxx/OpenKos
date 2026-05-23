<?php

test('registration screen returns 404', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('registration store returns 404', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
});
