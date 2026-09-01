<?php

use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

test('a user can log out and receive a confirmation message', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.auth.logout'))
        ->assertOk()
        ->assertJson(['message' => 'Logged out successfully.']);
});

test('logout requires authentication', function () {
    $this->postJson(route('api.v1.auth.logout'))
        ->assertStatus(401);
});

test('a user can refresh their token', function () {
    $user = User::factory()->create();
    $originalToken = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer {$originalToken}")
        ->postJson(route('api.v1.auth.refresh'));

    $response
        ->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

    expect($response->json('access_token'))
        ->toBeString()
        ->not->toBeEmpty()
        ->not->toBe($originalToken);
});
