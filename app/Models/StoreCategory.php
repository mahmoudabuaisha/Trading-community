<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

/** A section of the store added by the administrators, such as "Books". */
class StoreCategory extends Model
{
    use Translatable;

    public const TRANSLATABLE = ['name'];

    protected $fillable = ['name', 'position'];
}
