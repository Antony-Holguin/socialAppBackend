<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    /**
     * Return the authenticated user's profile.
     *
     * Kept as a separate resource-style controller so the user entity can
     * grow into a full RESTful surface (update, delete, etc.) without
     * polluting AuthController.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make(JWTAuth::user()),
        ]);
    }
}
