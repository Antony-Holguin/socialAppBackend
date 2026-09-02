<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Owns the authentication lifecycle: account creation, credential
 * verification, token issuance/rotation/invalidation and the user-view
 * serialization that the Sakai client expects.
 *
 * Controllers stay HTTP-shaped; this class carries the rules.
 */
class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'active' => true,
        ]);

        return [
            'user' => $user,
            'token' => JWTAuth::fromUser($user),
        ];
    }

    /**
     * Verify email + password. Returns null when credentials are bad or
     * the account is inactive — the controller turns that into a 401.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}|null
     */
    public function attempt(array $credentials): ?array
    {
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->active || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return [
            'user' => $user,
            'token' => JWTAuth::fromUser($user),
        ];
    }

    /**
     * Rotate the JWT using the current request's bearer/cookie. Returns
     * null when the token is invalid (controller → 401).
     *
     * @return array{user: User, token: string}|null
     */
    public function refresh(): ?array
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (JWTException $e) {
            return null;
        }

        return [
            'user' => JWTAuth::setToken($newToken)->toUser(),
            'token' => $newToken,
        ];
    }

    /**
     * Best-effort token invalidation. We swallow JWTException because the
     * caller wants to clear local cookies regardless of whether the server
     * still held a valid token.
     */
    public function logout(): void
    {
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (JWTException $e) {
            // Token already invalid or absent — still clear cookies.
        }
    }

    /**
     * The authenticated user — throws if the JWT guard couldn't resolve one.
     */
    public function currentUser(): User
    {
        /** @var User $user */
        $user = JWTAuth::user();

        return $user;
    }

    /**
     * Sakai-compatible AuthUserView — camelCase, exactly what the
     * TypeScript `AuthUser` interface expects.
     *
     * @return array<string, mixed>
     */
    public function userView(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'active' => (bool) $user->active,
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
