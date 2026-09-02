<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Roles\CreateRoleRequest;
use App\Http\Requests\Api\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roles) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->roles->paginate($request));
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        return response()->json(RoleResource::make($this->roles->create($request->validated())), 201);
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->roles->findById($id);

        return $role === null
            ? $this->notFound('Role', $id)
            : response()->json(RoleResource::make($role));
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = $this->roles->findById($id);
        if ($role === null) {
            return $this->notFound('Role', $id);
        }

        return response()->json(RoleResource::make($this->roles->update($role, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $role = $this->roles->findById($id);
        if ($role === null) {
            return $this->notFound('Role', $id);
        }

        // Refuse to delete the roles the system relies on for its own
        // bootstrap (admin powers every management route).
        if (in_array($role->name, ['admin', 'user'], true)) {
            return response()->json([
                'statusCode' => 422,
                'message' => "Role '{$role->name}' is system-managed and cannot be deleted.",
                'error' => 'Unprocessable Entity',
            ], 422);
        }

        $this->roles->delete($role);

        return response()->json(null, 204);
    }
}
