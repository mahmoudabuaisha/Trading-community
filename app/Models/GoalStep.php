<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A stage of a goal's roadmap (parent_id null) or a step inside a stage. */
class GoalStep extends Model
{
    protected $fillable = ['goal_id', 'parent_id', 'title', 'requirements', 'is_done', 'position'];

    protected $attributes = ['requirements' => '', 'is_done' => false];

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
