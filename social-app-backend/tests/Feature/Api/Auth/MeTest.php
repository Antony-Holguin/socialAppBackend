<?php

use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

test('an authenticated user can fetch their profile', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJson([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ])
        ->assertJsonMissingPath('data.password');
});

test('an unauthenticated request is rejected with 401 JSON', function () {
    $this->getJson(route('api.v1.auth.me'))
        ->assertStatus(401);
});
