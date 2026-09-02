<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => (bool) $this->active,
            'createdAt' => $this->created_at?->toIso8601String(),
            // `roles` is loaded for the list + for /auth/me. permissions are
            // derived from the already-loaded roles (no extra query) and
            // collapse duplicates so a user with multiple roles that share
            // permissions doesn't bloat the payload.
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->whenLoaded('roles', fn () => $this->roles
                ->flatMap->permissions->pluck('name')->unique()->values()
            ),
        ];
    }
}
