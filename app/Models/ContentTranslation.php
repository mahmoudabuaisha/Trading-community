<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    protected $fillable = ['field', 'locale', 'text', 'source_hash'];

    protected function casts(): array
    {
        return ['is_auto' => 'boolean'];
    }
}
