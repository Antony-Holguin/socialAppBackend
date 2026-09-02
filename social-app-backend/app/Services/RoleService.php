<?php

namespace App\Services;

use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Owns the role-management domain: list/create/update/delete roles and
 * sync their permission set.
 */
class RoleService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function paginate(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 20)));

        $query = Role::query()->where('guard_name', 'api')->orderBy('name');

        if (($q = $request->input('q')) !== null && $q !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($q).'%']);
        }

        $total = (clone $query)->count();
        $items = $query->with('permissions')->forPage($page, $limit)->get();

        return [
            'data' => RoleResource::collection($items)->resolve($request),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    public function findById(int $id): ?Role
    {
        return Role::where('guard_name', 'api')->with('permissions')->find($id);
    }

    /**
     * @param  array{name: string, permissions?: array<int, string>}  $data
     */
    public function create(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = Role::create($data + ['guard_name' => 'api']);

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        return $role->refresh()->load('permissions');
    }

    /**
     * @param  array{name?: string, permissions?: array<int, string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        if (isset($data['name'])) {
            $role->name = $data['name'];
        }

        // Save first if name changed, then sync permissions separately so a
        // duplicate-name violation surfaces before we touch the join table.
        $role->save();

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return $role->refresh()->load('permissions');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
