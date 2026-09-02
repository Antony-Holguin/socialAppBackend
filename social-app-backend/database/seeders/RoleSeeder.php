<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Map role name → list of permissions it carries. Every role is
     * guard_name='api' to match the default guard (see config/auth.php).
     */
    public const ROLE_PERMISSIONS = [
        'admin' => PermissionSeeder::PERMISSIONS, // every permission
        'user' => [
            'view-tasks',
            'create-tasks',
            'edit-tasks',
            'delete-tasks',
            'restore-tasks',
            'view-users',
        ],
    ];

    public function run(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'api',
            ]);

            // syncPermissions replaces the existing set — idempotent across re-seeds.
            $role->syncPermissions(
                Permission::whereIn('name', $permissions)
                    ->where('guard_name', 'api')
                    ->get(),
            );
        }
    }
}
