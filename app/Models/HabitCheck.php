<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitCheck extends Model
{
    protected $fillable = ['day'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }
}
