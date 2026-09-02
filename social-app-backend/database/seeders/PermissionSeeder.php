<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Granular per-resource permissions.
 *
 * guard_name is locked to 'api' (the default guard, see config/auth.php).
 * Spatie stamps guard_name from the default guard at create time, but we
 * pass it explicitly so the seeder stays correct if anyone ever changes
 * the default.
 */
class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        // Tasks
        'view-tasks',
        'create-tasks',
        'edit-tasks',
        'delete-tasks',
        'restore-tasks',
        // Users
        'view-users',
        'edit-users',
        'delete-users',
        'restore-users',
        // Roles
        'view-roles',
        'edit-roles',
        'delete-roles',
        // Permissions
        'view-permissions',
        'edit-permissions',
    ];

    public function run(): void
    {
        // Spatie caches permission lookups; flush so firstOrCreate after a
        // re-seed reflects the new state.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'api',
            ]);
        }
    }
}
