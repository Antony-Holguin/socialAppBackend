<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Mounted under the `/v1` prefix (see apiPrefix in bootstrap/app.php). This
| file contains every v1 endpoint; future breaking changes live in
| routes/api/v2.php, leaving v1 untouched for clients still on it.
|
*/

// Public health probe — useful for uptime checks and orchestration tooling.
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
    'time' => now()->toIso8601String(),
]))->name('api.v1.health');

// Authentication endpoints — public.
Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    // Authenticated sub-routes.
    Route::middleware('auth:api')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
    });
});

// Authenticated example resource — replace with the application's own
// controllers once the domain is in place.
Route::middleware('auth:api')->group(function (): void {
    Route::get('/user', [UserController::class, 'show'])->name('api.v1.user.show');
});

Route::fallback(fn (Request $request) => response()->json([
    'message' => 'The requested endpoint does not exist.',
    'version' => 'v1',
    'path' => $request->path(),
], 404));
