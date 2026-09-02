<?php

use App\Models\User;

test('admin can list users', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');
    User::factory()->count(3)->create();

    $this->withHeaders(authHeader($admin))
        ->getJson(route('api.v1.users.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'email', 'active', 'roles', 'permissions']], 'total', 'page', 'limit', 'totalPages'])
        ->assertJsonPath('total', 4); // admin + 3 factory users
});

test('non-admin (no view-users) cannot list users — 403', function () {
    $user = User::factory()->create();

    $this->withHeaders(authHeader($user))
        ->getJson(route('api.v1.users.index'))
        ->assertStatus(403);
});

test('admin can create a user with roles attached', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');

    $response = $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.users.store'), [
            'name' => 'New user',
            'email' => 'newuser@example.com',
            'password' => 'super-secret',
        ]);

    $response->assertCreated()
        ->assertJsonPath('email', 'newuser@example.com');

    $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
});

test('email must be unique', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');
    User::factory()->create(['email' => 'taken@example.com']);

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.users.store'), [
            'name' => 'Other',
            'email' => 'taken@example.com',
            'password' => 'super-secret',
        ])->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

test('admin can soft-delete a user (active=false)', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');
    $victim = User::factory()->create();

    $this->withHeaders(authHeader($admin))
        ->deleteJson(route('api.v1.users.destroy', $victim))
        ->assertOk()
        ->assertJsonPath('active', false);

    expect($victim->fresh()->active)->toBeFalse();
});

test('admin cannot deactivate themselves', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');

    $this->withHeaders(authHeader($admin))
        ->deleteJson(route('api.v1.users.destroy', $admin))
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot deactivate your own account.');
});

test('admin can restore a soft-deleted user', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');
    $victim = User::factory()->create(['active' => false]);

    $this->withHeaders(authHeader($admin))
        ->patchJson(route('api.v1.users.restore', $victim))
        ->assertOk()
        ->assertJsonPath('active', true);
});

test('admin can assign and remove a role', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');
    $target = User::factory()->create();

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.users.assignRole', $target), ['name' => 'user'])
        ->assertOk()
        ->assertJsonPath('roles', ['user']);

    $this->withHeaders(authHeader($admin))
        ->deleteJson(route('api.v1.users.removeRole', ['id' => $target->id, 'role' => 'user']))
        ->assertOk()
        ->assertJsonPath('roles', []);
});

test('admin cannot remove their own admin role', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');

    $this->withHeaders(authHeader($admin))
        ->deleteJson(route('api.v1.users.removeRole', ['id' => $admin->id, 'role' => 'admin']))
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot remove your own admin role.');
});

test('assigning a non-existent role returns 422', function () {
    $admin = asUserWithRole(User::factory()->create(), 'admin');
    $target = User::factory()->create();

    $this->withHeaders(authHeader($admin))
        ->postJson(route('api.v1.users.assignRole', $target), ['name' => 'does-not-exist'])
        ->assertStatus(422);
});

test('non-admin cannot create users — 403', function () {
    $user = User::factory()->create();

    $this->withHeaders(authHeader($user))
        ->postJson(route('api.v1.users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'super-secret',
        ])->assertStatus(403);
});

test('unauthenticated users get 401, not 403', function () {
    $this->getJson(route('api.v1.users.index'))->assertStatus(401);
});
