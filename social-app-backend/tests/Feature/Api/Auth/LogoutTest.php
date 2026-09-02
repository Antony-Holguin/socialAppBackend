<?php

use App\Http\Cookie\CookieService;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

test('a user can log out and receive a success response', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.auth.logout'));

    $response
        ->assertOk()
        ->assertExactJson(['success' => true]);

    // Both auth cookies should be evicted (max-age 0).
    foreach ($response->headers->getCookies() as $cookie) {
        if (in_array($cookie->getName(), [CookieService::COOKIE_ACCESS, CookieService::COOKIE_REFRESH], true)) {
            expect($cookie->getMaxAge())->toBe(0);
        }
    }
});

test('logout requires authentication', function () {
    $this->postJson(route('api.v1.auth.logout'))
        ->assertStatus(401)
        ->assertJsonStructure(['statusCode', 'message']);
});

test('a user can refresh their token', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.auth.refresh'));

    $response
        ->assertOk()
        ->assertJsonStructure(['id', 'name', 'email', 'active', 'createdAt'])
        ->assertJson(['id' => $user->id]);

    expect(collect($response->headers->getCookies())->first(
        fn ($c) => $c->getName() === CookieService::COOKIE_ACCESS
    ))->not->toBeNull();
});

test('refresh rejects when the token is missing or invalid', function () {
    $this->postJson(route('api.v1.auth.refresh'))
        ->assertStatus(401)
        ->assertJsonStructure(['statusCode', 'message']);
});
