<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\AssignRoleRequest;
use App\Http\Requests\Api\Users\CreateUserRequest;
use App\Http\Requests\Api\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->users->paginate($request));
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json(UserResource::make($user->load('roles.permissions')), 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->users->findById($id);

        return $user === null
            ? $this->notFound('User', $id)
            : response()->json(UserResource::make($user));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            return $this->notFound('User', $id);
        }

        return response()->json(UserResource::make($this->users->update($user, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            return $this->notFound('User', $id);
        }

        // Prevent an admin from soft-deleting themselves — would lock them
        // out of the management UI.
        if ($user->id === $this->user()->id) {
            return response()->json([
                'statusCode' => 422,
                'message' => 'You cannot deactivate your own account.',
                'error' => 'Unprocessable Entity',
            ], 422);
        }

        return response()->json(UserResource::make($this->users->softDelete($user)));
    }

    public function restore(int $id): JsonResponse
    {
        // Soft-delete is `active=false`, not Laravel's SoftDeletes trait — so a
        // plain find() returns inactive users too.
        $user = $this->users->findById($id);
        if ($user === null) {
            return $this->notFound('User', $id);
        }

        return response()->json(UserResource::make($this->users->restore($user)));
    }

    public function assignRole(AssignRoleRequest $request, int $id): JsonResponse
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            return $this->notFound('User', $id);
        }

        $updated = $this->users->assignRole($user, $request->validated('name'));

        if ($updated === null) {
            return response()->json([
                'statusCode' => 422,
                'message' => "Role '{$request->validated('name')}' does not exist for guard 'api'.",
                'error' => 'Unprocessable Entity',
            ], 422);
        }

        return response()->json(UserResource::make($updated));
    }

    public function removeRole(int $id, string $role): JsonResponse
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            return $this->notFound('User', $id);
        }

        // Prevent self-escalation reversal — an admin mustn't remove their
        // own admin role.
        if ($role === 'admin' && $user->id === $this->user()->id) {
            return response()->json([
                'statusCode' => 422,
                'message' => 'You cannot remove your own admin role.',
                'error' => 'Unprocessable Entity',
            ], 422);
        }

        return response()->json(UserResource::make($this->users->removeRole($user, $role)));
    }
}
