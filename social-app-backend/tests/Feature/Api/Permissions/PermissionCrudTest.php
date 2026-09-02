<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();
});

test('admin can list permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->withHeaders(authHeader($admin))
        ->getJson(route('api.v1.permissions.index'));

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'guardName']], 'total', 'page', 'limit', 'totalPages'])
        ->assertJsonPath('total', count(PermissionSeeder::PERMISSIONS));
});

test('admin can create a new permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.permissions.store'), ['name' => 'export-tasks'])
        ->assertCreated()
        ->assertJsonPath('name', 'export-tasks');

    $this->assertDatabaseHas('permissions', ['name' => 'export-tasks', 'guard_name' => 'api']);
});

test('permission names must be unique within the api guard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.permissions.store'), ['name' => 'view-tasks'])
        ->assertStatus(422);
});

test('admin can delete a non-system permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $permission = Permission::create(['name' => 'throwaway', 'guard_name' => 'api']);

    $this->withHeaders(authHeader($admin))
        ->deleteJson(route('api.v1.permissions.destroy', $permission))
        ->assertNoContent();

    expect(Permission::find($permission->id))->toBeNull();
});

test('seeded system permissions cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach (PermissionSeeder::PERMISSIONS as $name) {
        $perm = Permission::where('name', $name)->first();

        $this->withHeaders(authHeader($admin))
            ->deleteJson(route('api.v1.permissions.destroy', $perm))
            ->assertStatus(422)
            ->assertJsonPath('message', "Permission '{$name}' is system-managed and cannot be deleted.");
    }
});

test('non-admin cannot list permissions', function () {
    $user = User::factory()->create();

    $this->withHeaders(authHeader($user))
        ->getJson(route('api.v1.permissions.index'))
        ->assertStatus(403);
});
