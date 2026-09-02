<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Spatie caches permission lookups in a static array for the lifetime
        // of the process. Without flushing it, role/permission changes made
        // in one test leak into the next.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/
expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Stamp an Authorization header as the given user. Used by every CRUD test.
 * Defined here (not per-file) because Pest loads every test file into one
 * process — declaring it twice is a fatal "Cannot redeclare" error.
 */
function authHeader(User $user): array
{
    return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
}

/**
 * Run the permission + role seeders and assign a role to the user. Cheaper
 * than booting the seeder class in every test that needs an admin.
 */
function asUserWithRole(User $user, string $role): User
{
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();
    $user->assignRole($role);

    return $user->refresh()->load('roles.permissions');
}

function something()
{
    // ..
}
