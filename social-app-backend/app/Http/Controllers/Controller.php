<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class Controller
{
    /**
     * The currently authenticated user — resolved via the JWT guard.
     *
     * @throws JWTException
     */
    protected function user(): User
    {
        /** @var User $user */
        $user = JWTAuth::user();

        return $user;
    }

    /**
     * Standard 404 envelope. Callers pass the resource name so the message
     * is self-describing (e.g. "Task #5 not found", "Role #3 not found").
     *
     * Tasks use this to hide existence from non-owners — the same shape is
     * fine for global admin resources too.
     */
    protected function notFound(string $resource, int|string $id): JsonResponse
    {
        return response()->json([
            'statusCode' => 404,
            'message' => "{$resource} #{$id} not found",
            'error' => 'Not Found',
        ], 404);
    }
}
