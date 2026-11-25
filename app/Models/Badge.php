<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    /** What a badge can count. Each is a running total for the member. */
    public const METRICS = ['tasks_completed', 'streak_days', 'focus_minutes', 'goals_completed', 'habit_checks'];

    protected $fillable = ['title', 'description', 'metric', 'threshold', 'is_active'];

    protected $attributes = ['description' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['threshold' => 'integer', 'is_active' => 'boolean'];
    }

    /** The achievement key under which this badge is recorded once earned. */
    public function achievementKey(): string
    {
        return 'badge:'.$this->id;
    }

    protected static function booted(): void
    {
        // A deleted badge takes its awards with it, so nobody keeps a badge that no longer exists.
        static::deleted(fn (Badge $badge) => Achievement::where('key', $badge->achievementKey())->delete());
    }
}
