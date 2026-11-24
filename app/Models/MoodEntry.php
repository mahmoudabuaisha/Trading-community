<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoodEntry extends Model
{
    public const SCALE_MAX = 10;

    protected $fillable = ['day', 'mood', 'motivation'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d', 'mood' => 'integer', 'motivation' => 'integer'];
    }
}
