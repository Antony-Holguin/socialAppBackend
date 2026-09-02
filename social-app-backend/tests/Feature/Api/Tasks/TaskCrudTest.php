<?php

use App\Models\Task;
use App\Models\User;

test('a task can be created', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeader($user))
        ->postJson(route('api.v1.tasks.store'), ['title' => 'Comprar leche']);

    $response
        ->assertCreated()
        ->assertJsonStructure(['id', 'title', 'active', 'authorId', 'createdAt', 'updatedAt'])
        ->assertJson([
            'title' => 'Comprar leche',
            'active' => true,
            'authorId' => $user->id,
        ]);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Comprar leche',
        'active' => true,
        'author_id' => $user->id,
    ]);
});

test('author id is always taken from the JWT, never the body', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $response = $this->withHeaders(authHeader($user))
        ->postJson(route('api.v1.tasks.store'), [
            'title' => 'Injected task',
            'author_id' => $other->id,
        ]);

    $response->assertCreated()->assertJsonPath('authorId', $user->id);
});

test('creating a task requires a title of at least 2 chars', function () {
    $user = User::factory()->create();

    $this->withHeaders(authHeader($user))
        ->postJson(route('api.v1.tasks.store'), ['title' => 'a'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

test('listing tasks returns the paginated envelope', function () {
    $user = User::factory()->create();
    Task::factory()->count(3)->for($user, 'author')->create();

    $response = $this->withHeaders(authHeader($user))
        ->getJson(route('api.v1.tasks.index'));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'title', 'active', 'authorId', 'createdAt', 'updatedAt']],
            'total',
            'page',
            'limit',
            'totalPages',
        ])
        ->assertJsonPath('total', 3)
        ->assertJsonPath('page', 1)
        ->assertJsonPath('limit', 20)
        ->assertJsonPath('totalPages', 1);
});

test('mine returns only the authenticated user\'s tasks', function () {
    $me = User::factory()->create();
    Task::factory()->count(2)->for($me, 'author')->create();
    Task::factory()->count(3)->create(); // other authors

    $response = $this->withHeaders(authHeader($me))
        ->getJson(route('api.v1.tasks.mine'));

    $response
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonCount(2, 'data');
});

test('list filters by active and by free-text q', function () {
    $user = User::factory()->create();
    Task::factory()->for($user, 'author')->create(['title' => 'Comprar leche', 'active' => true]);
    Task::factory()->for($user, 'author')->create(['title' => 'Pagar luz', 'active' => false]);
    Task::factory()->for($user, 'author')->create(['title' => 'Comprar pan', 'active' => true]);

    $response = $this->withHeaders(authHeader($user))
        ->getJson(route('api.v1.tasks.index').'?active=true&q=comprar');

    $response->assertOk()->assertJsonPath('total', 2);
});

test('a user can see one of their own tasks', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user, 'author')->create();

    $this->withHeaders(authHeader($user))
        ->getJson(route('api.v1.tasks.show', $task))
        ->assertOk()
        ->assertJsonPath('id', $task->id);
});

test('a non-owner gets 404 on show — not 403 — to avoid id enumeration', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $task = Task::factory()->for($owner, 'author')->create();

    $this->withHeaders(authHeader($intruder))
        ->getJson(route('api.v1.tasks.show', $task))
        ->assertStatus(404)
        ->assertJson(['statusCode' => 404, 'error' => 'Not Found']);
});

test('a task can be partially updated by its author', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user, 'author')->create(['title' => 'Old title']);

    $this->withHeaders(authHeader($user))
        ->patchJson(route('api.v1.tasks.update', $task), ['title' => 'New title'])
        ->assertOk()
        ->assertJsonPath('title', 'New title');

    expect($task->fresh()->title)->toBe('New title');
});

test('a non-owner gets 404 on update', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $task = Task::factory()->for($owner, 'author')->create();

    $this->withHeaders(authHeader($intruder))
        ->patchJson(route('api.v1.tasks.update', $task), ['title' => 'Hijack'])
        ->assertStatus(404);
});

test('delete is a soft-delete: it flips active to false', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user, 'author')->create(['active' => true]);

    $this->withHeaders(authHeader($user))
        ->deleteJson(route('api.v1.tasks.destroy', $task))
        ->assertOk()
        ->assertJsonPath('active', false);

    expect($task->fresh()->active)->toBeFalse();
});

test('restore re-activates a soft-deleted task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user, 'author')->inactive()->create();

    $this->withHeaders(authHeader($user))
        ->patchJson(route('api.v1.tasks.restore', $task))
        ->assertOk()
        ->assertJsonPath('active', true);

    expect($task->fresh()->active)->toBeTrue();
});

test('the tasks endpoints require authentication', function () {
    $this->getJson(route('api.v1.tasks.index'))->assertStatus(401);
    $this->getJson(route('api.v1.tasks.mine'))->assertStatus(401);
    $this->postJson(route('api.v1.tasks.store'), ['title' => 'x'])->assertStatus(401);
});
