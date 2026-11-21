<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReview extends Model
{
    protected $fillable = ['date', 'win', 'unfinished', 'lesson', 'tomorrow'];

    protected $attributes = ['win' => '', 'unfinished' => '', 'lesson' => '', 'tomorrow' => ''];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
