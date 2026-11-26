<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An achievement defined by the administrators: when all its conditions hold
 * within its period, the member earns it and its points.
 */
class Badge extends Model
{
    use Translatable;

    /** Fields members read in their own language. */
    public const TRANSLATABLE = ['title', 'description'];

    /** What a condition can count, within the badge's period. */
    public const METRICS = ['tasks_completed', 'perfect_days', 'streak_days', 'focus_minutes', 'goals_completed', 'habit_checks'];

    /** "once" is earned a single time over all history; the others can be earned again every period. */
    public const PERIODS = ['once', 'daily', 'weekly', 'monthly', 'yearly'];

    protected $fillable = ['title', 'description', 'points', 'period', 'conditions', 'is_active', 'reward_item_id'];

    protected $attributes = ['description' => '', 'is_active' => true, 'points' => 0, 'period' => 'once'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'conditions' => 'array', 'is_active' => 'boolean'];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(BadgeAward::class);
    }

    /** The key under which this badge appears among a member's achievements. */
    public function achievementKey(): string
    {
        return 'badge:'.$this->id;
    }
}
