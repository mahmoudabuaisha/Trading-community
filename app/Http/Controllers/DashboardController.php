<?php

namespace App\Http\Controllers;

use App\Http\Resources\AchievementResource;
use App\Http\Resources\BadgeResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DailyReviewResource;
use App\Http\Resources\GoalResource;
use App\Http\Resources\MotivationResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Models\Badge;
use App\Models\Motivation;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        // Whole days the member stayed away before this visit (0 when they were here yesterday or today).
        $lastDay = $user->last_seen_at?->setTimezone($user->timezone)->toDateString();
        $absenceDays = $lastDay === null ? 0 : max(0, (int) CarbonImmutable::parse($lastDay)->diffInDays(CarbonImmutable::parse($day)) - 1);
        if ($lastDay !== $day) {
            $user->forceFill(['last_seen_at' => now()])->save();
        }

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
            'absenceDays' => $absenceDays,
            'rewards' => ['pointsPerPerfectDay' => (int) config('rewards.points_per_perfect_day'), 'frames' => config('rewards.frames'), 'titles' => config('rewards.titles')],
            'badges' => BadgeResource::collection(Badge::where('is_active', true)->orderBy('id')->get()),
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
