<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\HabitCheck;
use App\Models\Task;
use App\Services\AchievementService;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** "My summit": the numbers the 3D world is built from. */
class WorldController extends Controller
{
    public function show(Request $request, PointsService $points, AchievementService $achievements): JsonResponse
    {
        $user = $request->user();
        $done = $user->tasks()->where('status', Task::STATUS_COMPLETED);

        return response()->json([
            'name' => $user->display_name,
            'tasksCompleted' => (clone $done)->count(),
            'areas' => $user->categories()->orderBy('id')->get()->map(fn ($category): array => [
                'name' => $category->name,
                'color' => $category->color,
                'done' => (clone $done)->where('category_id', $category->id)->count(),
            ]),
            'goalsCompleted' => $user->goals()->where('status', Goal::STATUS_COMPLETED)->latest('updated_at')->limit(12)->pluck('title'),
            'habitChecks' => HabitCheck::whereIn('habit_id', $user->habits()->select('id'))->whereDate('day', '>=', now()->subDays(30)->toDateString())->count(),
            'streak' => $achievements->currentStreak($user),
            'pointsEarned' => $points->earned($user),
            'skillLevels' => DB::table('skill_progress')->where('user_id', $user->id)->count(),
            ...array_intersect_key($points->appearance($user), array_flip(['title', 'theme'])),
        ]);
    }
}
