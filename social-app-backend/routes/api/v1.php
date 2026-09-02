<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Mounted under the `/api/v1` prefix (see apiPrefix in bootstrap/app.php).
| Future breaking changes live in routes/api/v2.php, leaving v1 untouched.
|
*/

// Public health probe.
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
    'time' => now()->toIso8601String(),
]))->name('api.v1.health');

// Authentication endpoints — public.
Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');

    // Authenticated sub-routes.
    Route::middleware('auth:api')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// Tasks — owner-scoped CRUD. The `/mine` route MUST be declared before
// `/{id}` so 'mine' is not consumed as an integer parameter.
Route::prefix('tasks')->name('api.v1.tasks.')->middleware('auth:api')->group(function (): void {
    Route::get('/mine', [TaskController::class, 'mine'])->name('mine');
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::get('/{id}', [TaskController::class, 'show'])->name('show')->whereNumber('id');
    Route::patch('/{id}', [TaskController::class, 'update'])->name('update')->whereNumber('id');
    Route::delete('/{id}', [TaskController::class, 'destroy'])->name('destroy')->whereNumber('id');
    Route::patch('/{id}/restore', [TaskController::class, 'restore'])->name('restore')->whereNumber('id');
});

Route::fallback(fn (Request $request) => response()->json([
    'statusCode' => 404,
    'message' => 'The requested endpoint does not exist.',
    'error' => 'Not Found',
    'path' => $request->path(),
], 404));
