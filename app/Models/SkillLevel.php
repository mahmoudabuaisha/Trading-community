<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillLevel extends Model
{
    use Translatable;

    /** Fields members read in their own language. */
    public const TRANSLATABLE = ['title', 'description', 'challenge'];

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
