<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTipDraw extends Model
{
    protected $fillable = ['daily_tip_id', 'day', 'body'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    public function tip(): BelongsTo
    {
        return $this->belongsTo(DailyTip::class, 'daily_tip_id');
    }
}
