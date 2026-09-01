<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user and issue an access token in the same response.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->tokenResponse($token, $user, 201);
    }

    /**
     * Authenticate an existing user and issue an access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        try {
            $token = JWTAuth::attempt($credentials);
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Could not issue token. Please try again.',
            ], 500);
        }

        if (! $token) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        /** @var User $user */
        $user = JWTAuth::user();

        return $this->tokenResponse($token, $user);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = JWTAuth::user();

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    /**
     * Invalidate the current JWT.
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Could not invalidate token.',
            ], 500);
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Issue a fresh token using the current (still-valid) one and invalidate
     * the old one. Implements the refresh-rotation pattern recommended for
     * stateless JWT APIs.
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Could not refresh token.',
            ], 401);
        }

        /** @var User $user */
        $user = JWTAuth::user();

        return $this->tokenResponse($newToken, $user);
    }

    /**
     * Shape the standard token response (RFC-6750 bearer + user payload).
     */
    protected function tokenResponse(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => UserResource::make($user),
        ], $status);
    }
}
