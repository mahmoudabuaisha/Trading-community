<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const PERIOD_TYPES = ['daily', 'weekly', 'monthly', 'yearly'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'category_id', 'title', 'description', 'period_type', 'period_start', 'period_end', 'due_date', 'priority', 'status',
    ];

    protected $attributes = [
        'description' => '',
        'priority' => 'medium',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Invariant: completed_at is set exactly while the task is completed.
        // Enforced here so every write path (API, seeder, console) obeys it.
        static::saving(function (Task $task): void {
            if ($task->status === self::STATUS_COMPLETED) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
