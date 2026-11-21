<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function store(TaskRequest $request): JsonResponse
    {
        $task = $request->user()->tasks()->create($request->attributesForModel());

        return response()->json(['task' => new TaskResource($task->refresh())], 201);
    }

    public function update(TaskRequest $request, Task $task, AchievementService $achievements): JsonResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->attributesForModel());

        return response()->json([
            'task' => new TaskResource($task),
            'newAchievements' => AchievementResource::collection($achievements->evaluate($request->user())),
        ]);
    }

    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
