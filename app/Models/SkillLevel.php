<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillLevel extends Model
{
    /** Share of quiz questions that must be right to pass a level. */
    public const PASS_PERCENT = 70;

    protected $fillable = ['title', 'description', 'challenge', 'lesson_id', 'quiz', 'position'];

    protected $attributes = ['description' => '', 'challenge' => ''];

    protected function casts(): array
    {
        return ['quiz' => 'array'];
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(SkillPath::class, 'skill_path_id');
    }
}
