<?php

use App\Models\User;

test('a user can register and receive a JWT', function () {
    $payload = [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ];

    $response = $this->postJson(route('api.v1.auth.register'), $payload);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email'],
        ])
        ->assertJson([
            'token_type' => 'bearer',
            'user' => [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ],
        ]);

    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});

test('register validates required fields', function () {
    $this->postJson(route('api.v1.auth.register'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('register rejects duplicate email addresses', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
