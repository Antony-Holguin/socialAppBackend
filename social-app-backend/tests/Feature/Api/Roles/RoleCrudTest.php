<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();
});

test('admin can list roles with their permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->withHeaders(authHeader($admin))
        ->getJson(route('api.v1.roles.index'));

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'guardName', 'permissions']], 'total', 'page', 'limit', 'totalPages'])
        ->assertJsonPath('total', 2); // admin + user
});

test('admin can create a role with permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.roles.store'), [
            'name' => 'editor',
            'permissions' => ['view-tasks', 'edit-tasks'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'editor')
        ->assertJsonPath('permissions', ['view-tasks', 'edit-tasks']);

    $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'api']);
});

test('role name must be unique within the api guard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.roles.store'), ['name' => 'admin'])
        ->assertStatus(422);
});

test('admin can update a role and sync its permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $role = Role::create(['name' => 'reviewer', 'guard_name' => 'api']);

    $response = $this->withHeaders(authHeader($admin))
        ->patchJson(route('api.v1.roles.update', $role), [
            'name' => 'reviewer',
            'permissions' => ['view-tasks'],
        ]);

    $response->assertOk()
        ->assertJsonPath('permissions', ['view-tasks']);
});

test('admin can delete a non-system role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $role = Role::create(['name' => 'deletable', 'guard_name' => 'api']);

    $this->withHeaders(authHeader($admin))
        ->deleteJson(route('api.v1.roles.destroy', $role))
        ->assertNoContent();

    expect(Role::find($role->id))->toBeNull();
});

test('the system-managed roles cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach (['admin', 'user'] as $protected) {
        $this->withHeaders(authHeader($admin))
            ->deleteJson(route('api.v1.roles.destroy', Role::where('name', $protected)->first()))
            ->assertStatus(422)
            ->assertJsonPath('message', "Role '{$protected}' is system-managed and cannot be deleted.");
    }
});

test('non-admin cannot list roles', function () {
    $user = User::factory()->create();

    $this->withHeaders(authHeader($user))
        ->getJson(route('api.v1.roles.index'))
        ->assertStatus(403);
});

test('unknown permission names are rejected on create', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.roles.store'), [
            'name' => 'invalid-perms',
            'permissions' => ['made-up-permission'],
        ])->assertStatus(422);
});

test('the permissions returned for a role match what was assigned', function () {
    // Reach into Spatie to confirm the pivot carries every name (catches a
    // forgotten sync vs. a partial attach).
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'api']);
    $perms = ['view-tasks', 'view-users', 'view-roles'];
    $role->syncPermissions($perms);

    expect($role->refresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect($perms)->sort()->values()->all());
});
