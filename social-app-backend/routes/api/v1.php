<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
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

// Users — admin-gated. Read requires view-users; mutations require
// edit-users; soft-delete/restore require delete-users / restore-users.
Route::prefix('users')->name('api.v1.users.')->middleware(['auth:api', 'permission:view-users'])->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:edit-users')->name('store');
    Route::get('/{id}', [UserController::class, 'show'])->name('show')->whereNumber('id');
    Route::patch('/{id}', [UserController::class, 'update'])->middleware('permission:edit-users')->name('update')->whereNumber('id');
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:delete-users')->name('destroy')->whereNumber('id');
    Route::patch('/{id}/restore', [UserController::class, 'restore'])->middleware('permission:restore-users')->name('restore')->whereNumber('id');
    Route::post('/{id}/roles', [UserController::class, 'assignRole'])->middleware('permission:edit-users')->name('assignRole')->whereNumber('id');
    Route::delete('/{id}/roles/{role}', [UserController::class, 'removeRole'])->middleware('permission:edit-users')->name('removeRole')->where(['id' => '[0-9]+', 'role' => '[a-zA-Z0-9_-]+']);
});

// Roles — admin-gated.
Route::prefix('roles')->name('api.v1.roles.')->middleware(['auth:api', 'permission:view-roles'])->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->middleware('permission:edit-roles')->name('store');
    Route::get('/{id}', [RoleController::class, 'show'])->name('show')->whereNumber('id');
    Route::patch('/{id}', [RoleController::class, 'update'])->middleware('permission:edit-roles')->name('update')->whereNumber('id');
    Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:delete-roles')->name('destroy')->whereNumber('id');
});

// Permissions — admin-gated read; create/delete require edit-permissions.
Route::prefix('permissions')->name('api.v1.permissions.')->middleware(['auth:api', 'permission:view-permissions'])->group(function (): void {
    Route::get('/', [PermissionController::class, 'index'])->name('index');
    Route::post('/', [PermissionController::class, 'store'])->middleware('permission:edit-permissions')->name('store');
    Route::delete('/{id}', [PermissionController::class, 'destroy'])->middleware('permission:edit-permissions')->name('destroy')->whereNumber('id');
});

Route::fallback(fn (Request $request) => response()->json([
    'statusCode' => 404,
    'message' => 'The requested endpoint does not exist.',
    'error' => 'Not Found',
    'path' => $request->path(),
], 404));
