<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\Goal;
use App\Models\HabitCheck;
use App\Models\StorePurchase;
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

        $created = [];
        foreach (array_diff($earned, $owned) as $key) {
            try {
                $created[] = $user->achievements()->create(['key' => $key, 'earned_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request awarded it first; the unique index is the arbiter.
            }
        }

        return [...$created, ...$this->evaluateBadges($user, $completedTasks)];
    }

    /**
     * Badges defined by administrators. Each is checked against its current
     * period; the unique index on (user, badge, period) makes this idempotent.
     * Newly earned ones are returned as unsaved achievements so the caller can
     * announce them like the built-in ones.
     *
     * @return list<Achievement>
     */
    private function evaluateBadges(User $user, ?int $completedTasks = null): array
    {
        $badges = Badge::where('is_active', true)->get();
        if ($badges->isEmpty()) {
            return [];
        }
        $now = CarbonImmutable::now($this->timezone($user));
        $earnedKeys = BadgeAward::where('user_id', $user->id)->get(['badge_id', 'period_key'])
            ->map(fn (BadgeAward $award): string => $award->badge_id.'|'.$award->period_key)->flip();
        $values = [];
        $new = [];

        foreach ($badges as $badge) {
            [$periodKey, $start, $end] = self::period($badge->period, $now);
            if ($earnedKeys->has($badge->id.'|'.$periodKey) || ! $badge->conditions) {
                continue;
            }
            foreach ($badge->conditions as $condition) {
                $metric = $condition['metric'];
                $values[$badge->period][$metric] ??= $this->metric($user, $metric, $start, $end);
                if ($values[$badge->period][$metric] < (int) $condition['threshold']) {
                    continue 2;
                }
            }
            try {
                BadgeAward::create(['user_id' => $user->id, 'badge_id' => $badge->id, 'period_key' => $periodKey, 'points' => $badge->points, 'day' => $now->toDateString()]);
                if ($badge->reward_item_id) {
                    // The item that comes with it is the member's, free.
                    StorePurchase::firstOrCreate(['user_id' => $user->id, 'store_item_id' => $badge->reward_item_id], ['price' => 0]);
                }
                $new[] = new Achievement(['key' => $badge->achievementKey(), 'earned_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request awarded it first.
            }
        }

        return $new;
    }

    /**
     * The period a badge is currently in, in the member's local calendar.
     * Weeks start on Monday, like the weekly plan.
     *
     * @return array{0: string, 1: CarbonImmutable|null, 2: CarbonImmutable|null} key, first day, last day
     */
    public static function period(string $period, CarbonImmutable $now): array
    {
        $today = $now->startOfDay();

        return match ($period) {
            'daily' => [$today->toDateString(), $today, $today],
            'weekly' => ['W'.$today->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), $today->startOfWeek(CarbonImmutable::MONDAY), $today->startOfWeek(CarbonImmutable::MONDAY)->addDays(6)],
            'monthly' => [$today->format('Y-m'), $today->startOfMonth(), $today->endOfMonth()->startOfDay()],
            'yearly' => [$today->format('Y'), $today->startOfYear(), $today->endOfYear()->startOfDay()],
            default => ['once', null, null],
        };
    }

    /** A member's total for one metric between two local days (inclusive), or over all time. */
    private function metric(User $user, string $metric, ?CarbonImmutable $start, ?CarbonImmutable $end): int
    {
        $days = fn ($query, string $column) => $start ? $query->whereDate($column, '>=', $start->toDateString())->whereDate($column, '<=', $end->toDateString()) : $query;
        $instants = fn ($query, string $column) => $start ? $query->where($column, '>=', $start->utc())->where($column, '<', $end->addDay()->utc()) : $query;

        return match ($metric) {
            'tasks_completed' => $instants($user->tasks()->where('status', Task::STATUS_COMPLETED), 'completed_at')->count(),
            'perfect_days' => $this->perfectDays($user, $start, $end),
            'streak_days' => $this->currentStreak($user),
            'focus_minutes' => (int) $days($user->focusSessions(), 'day')->sum('minutes'),
            'goals_completed' => $instants($user->goals()->where('status', Goal::STATUS_COMPLETED), 'updated_at')->count(),
            'habit_checks' => $days(HabitCheck::whereIn('habit_id', $user->habits()->select('id')), 'day')->count(),
            default => 0,
        };
    }

    /** Days on which the member had daily tasks and completed every one of them (cancelled tasks do not count). */
    public function perfectDays(User $user, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null): int
    {
        $query = $user->tasks()->where('period_type', 'daily')->where('status', '!=', 'cancelled');
        if ($start) {
            $query->whereDate('period_start', '>=', $start->toDateString())->whereDate('period_start', '<=', $end->toDateString());
        }

        return $query->selectRaw('period_start')->groupBy('period_start')
            ->havingRaw('COUNT(*) = SUM(CASE WHEN status = ? THEN 1 ELSE 0 END)', [Task::STATUS_COMPLETED])
            ->get()->count();
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
