<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeAward extends Model
{
    protected $fillable = ['user_id', 'badge_id', 'period_key', 'points', 'day'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'day' => 'date:Y-m-d'];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
