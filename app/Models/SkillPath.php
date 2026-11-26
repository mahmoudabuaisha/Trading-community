<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillPath extends Model
{
    use Translatable;

    /** Fields members read in their own language. */
    public const TRANSLATABLE = ['title', 'description'];

    protected $fillable = ['title', 'description', 'is_active'];

    protected $attributes = ['description' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function isSharedContent(): bool
    {
        return $this->user_id === null;
    }

    public function levels(): HasMany
    {
        return $this->hasMany(SkillLevel::class)->orderBy('position')->orderBy('id');
    }
}
