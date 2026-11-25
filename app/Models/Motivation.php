<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class Motivation extends Model
{
    use Translatable;

    /** Fields members read in their own language. */
    public const TRANSLATABLE = ['title', 'body'];

    public const CATEGORIES = ['growth', 'motivation', 'announcement'];

    protected $fillable = ['title', 'body', 'category', 'is_active'];

    protected $attributes = ['category' => 'growth', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
