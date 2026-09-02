<?php

namespace App\Services;

use App\Http\Resources\PermissionResource;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * Owns the permission-management domain: list/create/delete permissions.
 * Permissions are immutable strings — there's no update verb.
 */
class PermissionService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function paginate(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 20)));

        $query = Permission::query()->where('guard_name', 'api')->orderBy('name');

        if (($q = $request->input('q')) !== null && $q !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($q).'%']);
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get();

        return [
            'data' => PermissionResource::collection($items)->resolve($request),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    public function findById(int $id): ?Permission
    {
        return Permission::where('guard_name', 'api')->find($id);
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Permission
    {
        return Permission::create($data + ['guard_name' => 'api']);
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
    }
}
