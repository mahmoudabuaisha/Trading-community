<?php

namespace App\Http\Controllers;

use App\Http\Requests\FocusSessionRequest;
use App\Http\Resources\AchievementResource;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;

class FocusSessionController extends Controller
{
    public function store(FocusSessionRequest $request, AchievementService $achievements): JsonResponse
    {
        $user = $request->user();
        $user->focusSessions()->create($request->attributesForModel());

        return response()->json([
            'focus' => DashboardController::focusTotals($user, $request->validated('day')),
            'newAchievements' => AchievementResource::collection($achievements->evaluate($user)),
        ], 201);
    }
}
