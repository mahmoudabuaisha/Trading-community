<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineEntry extends Model
{
    protected $fillable = ['event_key', 'day', 'title', 'note'];

    protected $attributes = ['note' => ''];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }
}
