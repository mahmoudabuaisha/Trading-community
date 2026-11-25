<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

/** Points for commitment, and what they unlock. */
class RewardService
{
    /** Days on which the member had daily tasks and completed every one of them (cancelled tasks do not count). */
    public function perfectDays(User $user): int
    {
        return $user->tasks()
            ->where('period_type', 'daily')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('period_start, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [Task::STATUS_COMPLETED])
            ->groupBy('period_start')
            ->havingRaw('COUNT(*) = SUM(CASE WHEN status = ? THEN 1 ELSE 0 END)', [Task::STATUS_COMPLETED])
            ->get()
            ->count();
    }

    public function points(User $user): int
    {
        return $this->perfectDays($user) * (int) config('rewards.points_per_perfect_day');
    }

    public function canEquipFrame(User $user, string $frame): bool
    {
        $cost = config("rewards.frames.$frame");

        return $cost !== null && $this->points($user) >= $cost;
    }

    public function canEquipTitle(User $user, ?string $title): bool
    {
        if ($title === null) {
            return true;
        }
        $cost = config("rewards.titles.$title");

        return $cost !== null && $this->points($user) >= $cost;
    }

    /**
     * What the member currently shows. A reward they can no longer afford
     * (points fell after un-completing tasks) is not shown, without being lost.
     *
     * @return array{points: int, frame: string, titleKey: string|null}
     */
    public function summary(User $user): array
    {
        $points = $this->points($user);
        $frameCost = config('rewards.frames.'.$user->frame);
        $titleCost = $user->title_key === null ? null : config('rewards.titles.'.$user->title_key);

        return [
            'points' => $points,
            'frame' => $frameCost !== null && $points >= $frameCost ? $user->frame : 'none',
            'titleKey' => $titleCost !== null && $points >= $titleCost ? $user->title_key : null,
        ];
    }
}
