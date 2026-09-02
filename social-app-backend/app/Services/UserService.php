<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Owns the user-management domain: listing, profile edits, soft-delete /
 * restore, and role assignment. Global admin resource — no ownership rule
 * (unlike TaskService), so any route that hits a method here is already
 * gated by `permission:view-users` (or `edit-users`) at the router.
 */
class UserService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function paginate(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 20)));

        $query = User::query()->orderByDesc('id');

        if ($request->has('active')) {
            $value = $request->input('active');
            if ($value !== 'all') {
                $query->where('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
            }
        } else {
            // Default: only active users. Pass ?active=false or ?active=all
            // to widen.
            $query->where('active', true);
        }

        if (($q = $request->input('q')) !== null && $q !== '') {
            $needle = '%'.strtolower($q).'%';
            $query->where(function ($w) use ($needle) {
                $w->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }

        $total = (clone $query)->count();
        $items = $query->with('roles.permissions')->forPage($page, $limit)->get();

        return [
            'data' => UserResource::collection($items)->resolve($request),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    /**
     * Find a user by id with roles+permissions eager-loaded. Returns null
     * if not found.
     */
    public function findById(int $id): ?User
    {
        return User::with('roles.permissions')->find($id);
    }

    /**
     * Partial update. Returns null when the user doesn't exist.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->fill($data);
        $user->save();

        return $user->refresh()->load('roles.permissions');
    }

    /**
     * Soft delete: flips `active` to false. Same convention as tasks.
     */
    public function softDelete(User $user): User
    {
        $user->active = false;
        $user->save();

        return $user->refresh();
    }

    public function restore(User $user): User
    {
        $user->active = true;
        $user->save();

        return $user->refresh()->load('roles.permissions');
    }

    /**
     * Assign a role by name. Returns the refreshed user (with relations
     * loaded) on success, null if the role doesn't exist.
     */
    public function assignRole(User $user, string $roleName): ?User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
        if ($role === null) {
            return null;
        }
        $user->assignRole($role);

        return $user->refresh()->load('roles.permissions');
    }

    /**
     * Remove a role by name. Returns the refreshed user. Removing a role
     * the user doesn't carry is a no-op (Spatie's behaviour).
     */
    public function removeRole(User $user, string $roleName): User
    {
        $user->removeRole($roleName);

        return $user->refresh()->load('roles.permissions');
    }
}
