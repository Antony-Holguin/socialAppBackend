<?php

namespace App\Services;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Owns the task domain: queries, mutations and the ownership rule
 * (non-owners never learn that a task with a given id exists — see
 * `findForUser()` returning null instead of throwing).
 *
 * Returns plain models / arrays — controllers wrap them in HTTP shape.
 */
class TaskService
{
    /**
     * Paginated list. Pass `$owner` to scope to that user's tasks
     * (used by /tasks/mine). Always ordered by id DESC for stable
     * pagination across requests.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function paginate(Request $request, ?User $owner = null): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 20)));

        $query = $this->buildQuery($request, $owner);

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get();

        return [
            'data' => TaskResource::collection($items)->resolve($request),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    /**
     * Translate the validated query string into a query builder. Filters:
     *  - `active` (bool, optional — omitted means BOTH states)
     *  - `q`      (string, case-insensitive substring on title)
     */
    protected function buildQuery(Request $request, ?User $owner): Builder
    {
        $query = Task::query()->orderByDesc('id');

        if ($owner !== null) {
            $query->where('author_id', $owner->id);
        }

        if ($request->has('active')) {
            $value = $request->input('active');
            // `active=all` (or any non-truthy non-falsy value) → both states.
            // `active=true|false|1|0` → just that state.
            if ($value !== 'all') {
                $query->where('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
            }
        } else {
            // No filter → only active rows. The default view must hide
            // soft-deleted (inactive) records.
            $query->where('active', true);
        }

        if (($q = $request->input('q')) !== null && $q !== '') {
            // LOWER(...) for cross-DB portability (SQLite + Postgres).
            $query->whereRaw('LOWER(title) LIKE ?', ['%'.strtolower($q).'%']);
        }

        return $query;
    }

    /**
     * Create a task owned by the authenticated user. `author_id` is ALWAYS
     * taken from the JWT — never from the body — to prevent impersonation.
     *
     * @param  array{title: string, active?: bool}  $data
     */
    public function create(User $author, array $data): Task
    {
        return Task::create([
            'title' => $data['title'],
            'active' => $data['active'] ?? true,
            'author_id' => $author->id,
        ]);
    }

    /**
     * Return the task if it exists AND belongs to `$user`. Returns null
     * otherwise — caller decides whether to render 404 (the standard
     * pattern that hides task existence from non-owners).
     */
    public function findForUser(User $user, int $id): ?Task
    {
        $task = Task::find($id);

        return ($task && $task->author_id === $user->id) ? $task : null;
    }

    /**
     * Partial update. Returns null when the task doesn't exist or doesn't
     * belong to the user.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $id, array $data): ?Task
    {
        $task = $this->findForUser($user, $id);

        if ($task === null) {
            return null;
        }

        $task->fill($data);
        $task->save();

        return $task;
    }

    /**
     * Soft delete: flips `active` to false. Returns null when not found /
     * not owned. The row stays in the table — use `restore()` to bring it
     * back.
     */
    public function softDelete(User $user, int $id): ?Task
    {
        $task = $this->findForUser($user, $id);

        if ($task === null) {
            return null;
        }

        $task->active = false;
        $task->save();

        return $task;
    }

    /**
     * Reactivate a soft-deleted task.
     */
    public function restore(User $user, int $id): ?Task
    {
        $task = $this->findForUser($user, $id);

        if ($task === null) {
            return null;
        }

        $task->active = true;
        $task->save();

        return $task;
    }
}
