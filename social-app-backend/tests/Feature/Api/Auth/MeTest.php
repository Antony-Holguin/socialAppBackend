<?php

use App\Http\Cookie\CookieService;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Models\User;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

test('an authenticated user can fetch their profile via Bearer token', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'active' => true,
        ])
        ->assertJsonStructure(['id', 'name', 'email', 'active', 'createdAt'])
        ->assertJsonMissingPath('data')
        ->assertJsonMissingPath('password');
});

test('the cookie → header middleware bridges httpOnly cookies to the JWT guard', function () {
    $user = User::factory()->create();

    // Build a real Symfony request carrying the httpOnly cookie, just like a
    // browser would after login.
    $token = JWTAuth::fromUser($user);
    $request = Request::create('/api/v1/auth/me', 'GET');
    $request->cookies->set(CookieService::COOKIE_ACCESS, $token);

    $middleware = new AuthenticateFromCookie(
        new CookieService,
    );

    $middleware->handle($request, fn ($req) => response()->json([
        'bearer' => $req->bearerToken() ? 'present' : 'missing',
    ]));

    // After the middleware runs, the request has a synthetic Authorization
    // header that the JWT guard can read.
    expect($request->bearerToken())->toBe($token);
    expect($request->headers->get('Authorization'))->toBe("Bearer {$token}");
});

test('an unauthenticated request is rejected with the NestJS-style 401', function () {
    $this->getJson(route('api.v1.auth.me'))
        ->assertStatus(401)
        ->assertJsonStructure(['statusCode', 'message']);
});
