<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Goal;
use App\Models\HabitCheck;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Awards milestone badges. Keys are machine values; titles live in the UI.
 *
 * `evaluate` is idempotent: it can run after any write and only ever returns
 * the badges created by this call.
 */
class AchievementService
{
    public const TASK_MILESTONES = ['first_task' => 1, 'tasks_10' => 10, 'tasks_50' => 50, 'tasks_100' => 100];

    public const STREAK_MILESTONES = ['streak_7' => 7, 'streak_30' => 30];

    public const FOCUS_MINUTES_MILESTONE = 600;

    /**
     * @return list<Achievement> newly earned achievements
     */
    public function evaluate(User $user): array
    {
        $owned = $user->achievements()->pluck('key')->all();
        $earned = [];
        $streak = null;

        $completedTasks = $user->tasks()->where('status', Task::STATUS_COMPLETED)->count();
        foreach (self::TASK_MILESTONES as $key => $threshold) {
            if ($completedTasks >= $threshold) {
                $earned[] = $key;
            }
        }

        if ($completedTasks > 0 && array_diff(array_keys(self::STREAK_MILESTONES), $owned)) {
            $streak = $this->currentStreak($user);
            foreach (self::STREAK_MILESTONES as $key => $days) {
                if ($streak >= $days) {
                    $earned[] = $key;
                }
            }
        }

        if ($user->goals()->where('status', Goal::STATUS_COMPLETED)->exists()) {
            $earned[] = 'first_goal';
        }

        if ((int) $user->focusSessions()->sum('minutes') >= self::FOCUS_MINUTES_MILESTONE) {
            $earned[] = 'focus_600';
        }

        // Badges defined by administrators: each compares one running total with its threshold.
        foreach (Badge::where('is_active', true)->get() as $badge) {
            if (in_array($badge->achievementKey(), $owned, true)) {
                continue;
            }
            $value = match ($badge->metric) {
                'tasks_completed' => $completedTasks,
                'streak_days' => $streak ??= $this->currentStreak($user),
                'focus_minutes' => (int) $user->focusSessions()->sum('minutes'),
                'goals_completed' => $user->goals()->where('status', Goal::STATUS_COMPLETED)->count(),
                'habit_checks' => HabitCheck::whereIn('habit_id', $user->habits()->select('id'))->count(),
                default => 0,
            };
            if ($value >= $badge->threshold) {
                $earned[] = $badge->achievementKey();
            }
        }

        $created = [];
        foreach (array_diff($earned, $owned) as $key) {
            try {
                $created[] = $user->achievements()->create(['key' => $key, 'earned_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request awarded it first; the unique index is the arbiter.
            }
        }

        return $created;
    }

    /**
     * Consecutive local days, ending today (or yesterday when nothing is done
     * yet today), on which the user completed at least one task.
     */
    public function currentStreak(User $user, ?CarbonImmutable $now = null): int
    {
        $timezone = $this->timezone($user);
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);

        $days = $user->tasks()
            ->where('status', Task::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $now->subDays(400)->utc())
            ->pluck('completed_at')
            ->map(fn ($instant) => CarbonImmutable::parse($instant)->setTimezone($timezone)->toDateString())
            ->flip();

        $cursor = $now->startOfDay();
        if (! $days->has($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;
        while ($days->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    private function timezone(User $user): string
    {
        return in_array($user->timezone, timezone_identifiers_list(), true) ? $user->timezone : 'UTC';
    }
}
