<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Motivation extends Model
{
    public const CATEGORIES = ['growth', 'motivation', 'announcement'];

    protected $fillable = ['title', 'body', 'category', 'is_active'];

    protected $attributes = ['category' => 'growth', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
