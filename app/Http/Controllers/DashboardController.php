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
use App\Models\BadgeAward;
use App\Models\Broadcast;
use App\Models\Motivation;
use App\Models\Setting;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, AchievementService $achievements): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        $user = $request->user();
        // Catch up on badges whose period rolled over or that were added since the last write.
        $achievements->evaluate($user);
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
            'achievements' => [...AchievementResource::collection($user->achievements()->orderBy('earned_at')->get())->resolve(), ...$this->currentBadges($user)],
            'motivations' => MotivationResource::collection(Motivation::where('is_active', true)->latest()->latest('id')->get()),
            'focus' => self::focusTotals($user, $day),
            'review' => $review ? new DailyReviewResource($review) : null,
            'content' => Setting::content(),
            'absenceDays' => $absenceDays,
            'broadcasts' => Broadcast::forDay($day),
            'badges' => BadgeResource::collection(Badge::where('is_active', true)->orderBy('id')->get()),
        ]);
    }

    /**
     * Admin-defined badges the member holds right now: one-time badges for good,
     * repeatable ones for the period in progress. Shaped like achievements.
     *
     * @return list<array{id: int, key: string, earnedAt: string, timesEarned: int}>
     */
    private function currentBadges(User $user): array
    {
        $now = CarbonImmutable::now(in_array($user->timezone, timezone_identifiers_list(), true) ? $user->timezone : 'UTC');
        $awards = BadgeAward::where('user_id', $user->id)->with('badge')->orderBy('id')->get()->groupBy('badge_id');

        return $awards->map(function ($group) use ($now): ?array {
            $badge = $group->first()->badge;
            $current = $group->firstWhere('period_key', AchievementService::period($badge->period, $now)[0]);

            return $current ? ['id' => $current->id, 'key' => $badge->achievementKey(), 'earnedAt' => $current->created_at->toISOString(), 'timesEarned' => $group->count()] : null;
        })->filter()->values()->all();
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
