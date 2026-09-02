<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Cookie\CookieService;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $auth,
        protected CookieService $cookies,
    ) {}

    /**
     * Create a new user and stamp httpOnly auth cookies.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return $this->respondWithAuth($result['user'], $result['token'], 201);
    }

    /**
     * Exchange email+password for httpOnly auth cookies.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->attempt($request->validated());

        if ($result === null) {
            return response()->json([
                'statusCode' => 401,
                'message' => 'Invalid credentials',
                'error' => 'Unauthorized',
            ], 401);
        }

        return $this->respondWithAuth($result['user'], $result['token']);
    }

    /**
     * Rotate the JWT using the refresh cookie. Re-stamps both cookies.
     */
    public function refresh(): JsonResponse
    {
        $result = $this->auth->refresh();

        if ($result === null) {
            return response()->json([
                'statusCode' => 401,
                'message' => 'Invalid refresh token',
                'error' => 'Unauthorized',
            ], 401);
        }

        return response()
            ->json($this->auth->userView($result['user']))
            ->withCookies($this->cookies->setAuthCookies($result['token'], $result['token']));
    }

    /**
     * Invalidate the current token and clear both auth cookies.
     */
    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return response()
            ->json(['success' => true])
            ->withCookies($this->cookies->clearAuthCookies());
    }

    /**
     * Return the authenticated user.
     */
    public function me(): JsonResponse
    {
        return response()->json($this->auth->userView($this->auth->currentUser()));
    }

    /**
     * Shape the standard successful-auth response: user view in the body,
     * both auth cookies on the response.
     */
    protected function respondWithAuth(User $user, string $token, int $status = 200): JsonResponse
    {
        return response()
            ->json($this->auth->userView($user), $status)
            ->withCookies($this->cookies->setAuthCookies($token, $token));
    }
}
