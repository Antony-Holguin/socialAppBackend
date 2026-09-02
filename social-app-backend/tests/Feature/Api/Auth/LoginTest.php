<?php

use App\Http\Cookie\CookieService;
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
        ->assertJsonStructure(['id', 'name', 'email', 'active', 'createdAt'])
        ->assertJson(['email' => 'grace@example.com', 'active' => true])
        ->assertJsonMissingPath('access_token');

    $cookies = $response->headers->getCookies();
    expect(collect($cookies)->first(fn ($c) => $c->getName() === CookieService::COOKIE_ACCESS))->not->toBeNull();
    expect(collect($cookies)->first(fn ($c) => $c->getName() === CookieService::COOKIE_REFRESH))->not->toBeNull();
});

test('login rejects invalid credentials with the NestJS-style error shape', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => 'super-secret-password',
    ]);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'grace@example.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(401)
        ->assertJson([
            'statusCode' => 401,
            'message' => 'Invalid credentials',
            'error' => 'Unauthorized',
        ]);
});

test('login rejects inactive users', function () {
    User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => 'super-secret-password',
        'active' => false,
    ]);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'inactive@example.com',
        'password' => 'super-secret-password',
    ])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Invalid credentials');
});

test('login requires email and password', function () {
    $this->postJson(route('api.v1.auth.login'), [])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message']);
});
