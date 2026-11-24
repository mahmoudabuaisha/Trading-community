<?php

namespace App\Http\Controllers;

use App\Http\Resources\AchievementResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DailyReviewResource;
use App\Http\Resources\GoalResource;
use App\Http\Resources\MotivationResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Models\Motivation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        $user = $request->user();
        // "Today" is the caller's local calendar day, supplied by the client.
        $day = $request->query('date', now($user->timezone)->toDateString());
        $review = $user->dailyReviews()->whereDate('date', $day)->first();

        return response()->json([
            'user' => new UserResource($user),
            'tasks' => TaskResource::collection($user->tasks()->latest()->latest('id')->get()),
            'categories' => CategoryResource::collection($user->categories()->orderBy('id')->get()),
            'goals' => GoalResource::collection($user->goals()->latest()->latest('id')->get()),
            'achievements' => AchievementResource::collection($user->achievements()->orderBy('earned_at')->get()),
            'motivations' => MotivationResource::collection(Motivation::where('is_active', true)->latest()->latest('id')->get()),
            'focus' => self::focusTotals($user, $day),
            'review' => $review ? new DailyReviewResource($review) : null,
            'content' => Setting::content(),
        ]);
    }

    /**
     * @return array{totalMinutes: int, todayMinutes: int}
     */
    public static function focusTotals(User $user, string $day): array
    {
        return [
            'totalMinutes' => (int) $user->focusSessions()->sum('minutes'),
            'todayMinutes' => (int) $user->focusSessions()->whereDate('day', $day)->sum('minutes'),
        ];
    }
}
