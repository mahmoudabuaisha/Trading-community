<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillPath extends Model
{
    protected $fillable = ['title', 'description', 'is_active'];

    protected $attributes = ['description' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(SkillLevel::class)->orderBy('position')->orderBy('id');
    }
}
