<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitNote extends Model
{
    protected $fillable = ['month', 'body'];
}
