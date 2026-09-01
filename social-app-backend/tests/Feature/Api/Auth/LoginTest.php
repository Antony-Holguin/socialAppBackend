<?php

use App\Models\User;

test('a user can log in with valid credentials', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => 'super-secret-password',
    ]);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'grace@example.com',
        'password' => 'super-secret-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email'],
        ])
        ->assertJson(['token_type' => 'bearer']);
});

test('login rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => 'super-secret-password',
    ]);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'grace@example.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(401)
        ->assertJson(['message' => 'The provided credentials are incorrect.']);
});

test('login requires email and password', function () {
    $this->postJson(route('api.v1.auth.login'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
