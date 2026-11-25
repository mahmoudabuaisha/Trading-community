<?php

namespace App\Services;

use App\Models\HabitCheck;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * "Performance DNA": how a member actually works, read from what they did.
 * Everything is returned as numbers and machine keys; wording belongs to the UI.
 */
class InsightsService
{
    private const WINDOW_DAYS = 90;

    /** Below this many completed tasks the patterns are noise, so no conclusions are drawn. */
    private const MIN_TASKS = 5;

    public function analyse(User $user, string $today): array
    {
        $timezone = in_array($user->timezone, timezone_identifiers_list(), true) ? $user->timezone : 'UTC';
        $end = CarbonImmutable::parse($today, $timezone)->endOfDay();
        $start = $end->subDays(self::WINDOW_DAYS - 1)->startOfDay();

        $tasks = $user->tasks()->where('created_at', '>=', $start->subDays(30)->utc())->orWhere(fn ($query) => $query->where('user_id', $user->id)->where('completed_at', '>=', $start->utc()))
            ->get(['id', 'category_id', 'priority', 'status', 'due_date', 'completed_at', 'created_at', 'period_type', 'period_start']);
        $done = $tasks->filter(fn (Task $task): bool => $task->status === Task::STATUS_COMPLETED && $task->completed_at !== null && $task->completed_at->gte($start))
            ->map(fn (Task $task): array => ['task' => $task, 'at' => CarbonImmutable::parse($task->completed_at)->setTimezone($timezone)]);

        // When: weekday (0 = Monday) and part of the day.
        $byWeekday = array_fill(0, 7, 0);
        $byHour = array_fill(0, 24, 0);
        $perDay = [];
        foreach ($done as $entry) {
            $byWeekday[$entry['at']->dayOfWeekIso - 1]++;
            $byHour[$entry['at']->hour]++;
            $perDay[$entry['at']->toDateString()] = ($perDay[$entry['at']->toDateString()] ?? 0) + 1;
        }
        $parts = ['morning' => [5, 12], 'afternoon' => [12, 17], 'evening' => [17, 22], 'night' => [22, 29]];
        $byPart = array_map(fn (array $range): int => array_sum(array_map(fn (int $hour): int => $byHour[$hour % 24], range($range[0], $range[1] - 1))), $parts);

        // What: how reliably each priority and category gets finished.
        $rate = fn ($group): array => ['total' => $group->count(), 'done' => $group->where('status', Task::STATUS_COMPLETED)->count()];
        $recent = $tasks->filter(fn (Task $task): bool => $task->created_at->gte($start) && $task->status !== 'cancelled');
        $byPriority = $recent->groupBy('priority')->map($rate);
        $categories = $user->categories()->pluck('name', 'id');
        $byCategory = $recent->whereNotNull('category_id')->groupBy('category_id')
            ->map(fn ($group, $id): array => ['name' => $categories[$id] ?? '', ...$rate($group)])->filter(fn (array $row): bool => $row['name'] !== '')->values();

        $due = $tasks->filter(fn (Task $task): bool => $task->due_date !== null && $task->status === Task::STATUS_COMPLETED && $task->completed_at !== null);
        $onTime = $due->filter(fn (Task $task): bool => CarbonImmutable::parse($task->completed_at)->setTimezone($timezone)->toDateString() <= $task->due_date->toDateString())->count();

        // Then and now: the last 30 days against the 30 before.
        $period = function (CarbonImmutable $from, CarbonImmutable $to) use ($user, $done, $tasks): array {
            $completed = $done->filter(fn (array $entry): bool => $entry['at']->betweenIncluded($from, $to))->count();
            $created = $tasks->filter(fn (Task $task): bool => $task->created_at->betweenIncluded($from, $to) && $task->status !== 'cancelled');

            return [
                'tasksCompleted' => $completed,
                'completionRate' => $created->count() ? (int) round($created->where('status', Task::STATUS_COMPLETED)->count() * 100 / $created->count()) : 0,
                'activeDays' => $done->filter(fn (array $entry): bool => $entry['at']->betweenIncluded($from, $to))->map(fn (array $entry): string => $entry['at']->toDateString())->unique()->count(),
                'focusMinutes' => (int) $user->focusSessions()->whereDate('day', '>=', $from->toDateString())->whereDate('day', '<=', $to->toDateString())->sum('minutes'),
                'habitChecks' => HabitCheck::whereIn('habit_id', $user->habits()->select('id'))->whereDate('day', '>=', $from->toDateString())->whereDate('day', '<=', $to->toDateString())->count(),
            ];
        };
        $current = $period($end->subDays(29)->startOfDay(), $end);
        $previous = $period($end->subDays(59)->startOfDay(), $end->subDays(30));

        $weeks = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = $end->startOfWeek(CarbonImmutable::MONDAY)->subWeeks($i);
            $weeks[] = ['start' => $weekStart->toDateString(), 'completed' => $done->filter(fn (array $entry): bool => $entry['at']->betweenIncluded($weekStart, $weekStart->addDays(6)->endOfDay()))->count()];
        }

        // Mood: tasks finished on better-than-usual days against the rest.
        $moods = $user->moodEntries()->whereDate('day', '>=', $start->toDateString())->whereNotNull('mood')->get(['day', 'mood']);
        $mood = null;
        if ($moods->count() >= 6) {
            $average = $moods->avg('mood');
            $tasksOn = fn ($entries): float => round($entries->avg(fn ($entry): int => $perDay[$entry->day->toDateString()] ?? 0) ?? 0, 1);
            [$high, $low] = $moods->partition(fn ($entry): bool => $entry->mood >= $average);
            $mood = $high->count() && $low->count() ? ['highDays' => $tasksOn($high), 'lowDays' => $tasksOn($low)] : null;
        }

        $enough = $done->count() >= self::MIN_TASKS;
        $bestWeekday = $enough ? array_search(max($byWeekday), $byWeekday, true) : null;
        $activeWeekdays = array_filter($byWeekday);
        $weakWeekday = $enough && count($activeWeekdays) === 7 ? array_search(min($byWeekday), $byWeekday, true) : ($enough ? array_search(0, $byWeekday, true) : null);
        $bestPart = $enough ? array_search(max($byPart), $byPart, true) : null;

        return [
            'windowDays' => self::WINDOW_DAYS,
            'enoughData' => $enough,
            'totalCompleted' => $done->count(),
            'byWeekday' => $byWeekday,
            'byHour' => $byHour,
            'byPart' => $byPart,
            'bestWeekday' => $bestWeekday === false ? null : $bestWeekday,
            'weakWeekday' => $weakWeekday === false ? null : $weakWeekday,
            'bestPart' => $bestPart ?: null,
            'byPriority' => (object) $byPriority->all(),
            'byCategory' => $byCategory,
            'onTime' => ['total' => $due->count(), 'onTime' => $onTime],
            'current' => $current,
            'previous' => $previous,
            'weeks' => $weeks,
            'mood' => $mood,
            'streak' => app(AchievementService::class)->currentStreak($user),
            'tips' => $enough ? $this->tips($bestPart ?: null, $bestWeekday, $weakWeekday, $byPriority->all(), $byCategory->all(), $due->count(), $onTime, $current, $previous, $mood) : [],
        ];
    }

    /**
     * Advice as machine keys with their numbers; the UI words them.
     *
     * @return list<array{key: string, params: array<string, int|float|string>}>
     */
    private function tips(?string $part, int|false|null $best, int|false|null $weak, array $byPriority, array $byCategory, int $due, int $onTime, array $current, array $previous, ?array $mood): array
    {
        $tips = [];
        if ($part) {
            $tips[] = ['key' => 'peakPart', 'params' => ['part' => $part]];
        }
        if (is_int($best)) {
            $tips[] = ['key' => 'bestDay', 'params' => ['weekday' => $best]];
        }
        if (is_int($weak) && $weak !== $best) {
            $tips[] = ['key' => 'weakDay', 'params' => ['weekday' => $weak]];
        }
        if ($due >= 4 && $onTime * 100 / $due < 60) {
            $tips[] = ['key' => 'late', 'params' => ['pct' => (int) round($onTime * 100 / $due)]];
        }
        $urgent = $byPriority['urgent'] ?? $byPriority['high'] ?? null;
        $low = $byPriority['low'] ?? null;
        if ($urgent && $low && $urgent['total'] >= 3 && $low['total'] >= 3 && $urgent['done'] / $urgent['total'] < $low['done'] / $low['total']) {
            $tips[] = ['key' => 'priorities', 'params' => []];
        }
        $weakest = collect($byCategory)->filter(fn (array $row): bool => $row['total'] >= 4)->sortBy(fn (array $row): float => $row['done'] / $row['total'])->first();
        if ($weakest && $weakest['done'] / $weakest['total'] < 0.5) {
            $tips[] = ['key' => 'category', 'params' => ['name' => $weakest['name'], 'pct' => (int) round($weakest['done'] * 100 / $weakest['total'])]];
        }
        if ($previous['tasksCompleted'] >= 5) {
            $change = (int) round(($current['tasksCompleted'] - $previous['tasksCompleted']) * 100 / $previous['tasksCompleted']);
            if (abs($change) >= 15) {
                $tips[] = ['key' => $change > 0 ? 'improving' : 'declining', 'params' => ['pct' => abs($change)]];
            }
        }
        if ($mood && $mood['highDays'] > $mood['lowDays'] * 1.3) {
            $tips[] = ['key' => 'mood', 'params' => ['high' => $mood['highDays'], 'low' => $mood['lowDays']]];
        }
        if ($current['focusMinutes'] === 0) {
            $tips[] = ['key' => 'focus', 'params' => []];
        }

        return $tips;
    }
}
