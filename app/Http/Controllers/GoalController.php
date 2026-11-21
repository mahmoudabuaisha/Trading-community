<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GoalController extends Controller
{
    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $request->user()->goals()->create($request->attributesForModel());

        return response()->json(['goal' => new GoalResource($goal->refresh())], 201);
    }

    public function update(UpdateGoalRequest $request, Goal $goal, AchievementService $achievements): JsonResponse
    {
        Gate::authorize('update', $goal);

        $goal->update($request->attributesForModel());

        return response()->json([
            'goal' => new GoalResource($goal),
            'newAchievements' => AchievementResource::collection($achievements->evaluate($request->user())),
        ]);
    }

    public function destroy(Goal $goal): Response
    {
        Gate::authorize('delete', $goal);

        $goal->delete();

        return response()->noContent();
    }
}
