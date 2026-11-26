<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

/** A short line a member may draw as their tip of the day. */
class DailyTip extends Model
{
    use Translatable;

    public const TRANSLATABLE = ['body'];

    protected $fillable = ['body', 'is_active', 'created_by'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
