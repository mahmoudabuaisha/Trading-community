<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    /** Enough for a full routine while keeping the monthly grid readable. */
    public const MAX_PER_USER = 30;

    protected $fillable = ['name', 'target_days', 'position'];

    protected function casts(): array
    {
        return ['target_days' => 'integer', 'position' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(HabitCheck::class);
    }
}
