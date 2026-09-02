<?php

use App\Http\Cookie\CookieService;
use App\Models\User;

test('a user can register and receive a session via httpOnly cookies', function () {
    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'super-secret-password',
    ]);

    $response
        ->assertCreated()
        ->assertJson([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'active' => true,
        ])
        ->assertJsonStructure(['id', 'name', 'email', 'active', 'createdAt'])
        ->assertJsonMissingPath('access_token')
        ->assertJsonMissingPath('data');

    // Both cookies should be set, httpOnly, scoped under /api/v1.
    $cookies = $response->headers->getCookies();
    $access = collect($cookies)->first(fn ($c) => $c->getName() === CookieService::COOKIE_ACCESS);
    $refresh = collect($cookies)->first(fn ($c) => $c->getName() === CookieService::COOKIE_REFRESH);

    expect($access)->not->toBeNull();
    expect($access->isHttpOnly())->toBeTrue();
    expect($access->getPath())->toBe('/api/v1');
    expect($refresh)->not->toBeNull();
    expect($refresh->getPath())->toBe('/api/v1/auth');

    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});

test('register rejects missing fields with the NestJS-style error shape', function () {
    $response = $this->postJson(route('api.v1.auth.register'), []);

    $response
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message'])
        ->assertJsonPath('statusCode', 422);

    expect($response->json('message'))->toBeArray()->toContain('The name field is required.');
});

test('register rejects short passwords', function () {
    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'short',
    ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

test('register rejects duplicate email addresses', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => 'super-secret-password',
    ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});
