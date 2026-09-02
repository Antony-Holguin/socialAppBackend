<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Permissions\CreatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __construct(protected PermissionService $permissions) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->permissions->paginate($request));
    }

    public function store(CreatePermissionRequest $request): JsonResponse
    {
        return response()->json(PermissionResource::make($this->permissions->create($request->validated())), 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $permission = $this->permissions->findById($id);
        if ($permission === null) {
            return $this->notFound('Permission', $id);
        }

        // Refuse to delete permissions seeded by PermissionSeeder — without
        // them the management UI breaks (and the seeder is idempotent so
        // they come back on the next migrate:fresh --seed, but the UI is
        // useless in the meantime).
        if (in_array($permission->name, PermissionSeeder::PERMISSIONS, true)) {
            return response()->json([
                'statusCode' => 422,
                'message' => "Permission '{$permission->name}' is system-managed and cannot be deleted.",
                'error' => 'Unprocessable Entity',
            ], 422);
        }

        $this->permissions->delete($permission);

        return response()->json(null, 204);
    }
}
