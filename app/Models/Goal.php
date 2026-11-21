<?php

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['title', 'description', 'period_type', 'period_start', 'period_end', 'target_value', 'current_value'];

    protected $attributes = [
        'description' => '',
        'current_value' => 0,
        'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'target_value' => 'integer',
            'current_value' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Invariants: 1 <= target, 0 <= current <= target, and status is
        // derived from progress — never set independently.
        static::saving(function (Goal $goal): void {
            $goal->target_value = max(1, (int) $goal->target_value);
            $goal->current_value = max(0, min($goal->target_value, (int) $goal->current_value));
            $goal->status = $goal->current_value >= $goal->target_value ? self::STATUS_COMPLETED : self::STATUS_ACTIVE;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
