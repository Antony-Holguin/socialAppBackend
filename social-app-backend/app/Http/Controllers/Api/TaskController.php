<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tasks\CreateTaskRequest;
use App\Http\Requests\Api\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TaskController extends Controller
{
    public function __construct(protected TaskService $tasks) {}

    /**
     * List every task (paginated). Mirrors NestJS's GET /tasks — open view.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->tasks->paginate($request));
    }

    /**
     * List the authenticated user's tasks.
     *
     * IMPORTANT: this route MUST be declared before GET /{id} in the
     * router or 'mine' is parsed as the id parameter.
     */
    public function mine(Request $request): JsonResponse
    {
        return response()->json($this->tasks->paginate($request, $this->user()));
    }

    // aaaa
    public function store(CreateTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($this->user(), $request->validated());

        return response()->json(TaskResource::make($task), 201);
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->tasks->findForUser($this->user(), $id);

        return $task === null
            ? $this->notFound('Task', $id)
            : response()->json(TaskResource::make($task));
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = $this->tasks->update($this->user(), $id, $request->validated());

        return $task === null
            ? $this->notFound('Task', $id)
            : response()->json(TaskResource::make($task));
    }

    public function destroy(int $id): JsonResponse
    {
        $task = $this->tasks->softDelete($this->user(), $id);

        return $task === null
            ? $this->notFound('Task', $id)
            : response()->json(TaskResource::make($task));
    }

    public function restore(int $id): JsonResponse
    {
        $task = $this->tasks->restore($this->user(), $id);

        return $task === null
            ? $this->notFound('Task', $id)
            : response()->json(TaskResource::make($task));
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = JWTAuth::user();

        return $user;
    }
}
