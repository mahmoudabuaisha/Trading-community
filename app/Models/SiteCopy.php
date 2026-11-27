<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

/** A phrase of the login page or home page, rewritten by an administrator. */
class SiteCopy extends Model
{
    use Translatable;

    public const TRANSLATABLE = ['text'];

    /** Only phrases from these parts of the interface can be replaced. */
    public const SECTIONS = ['auth', 'dash', 'center'];

    protected $fillable = ['key', 'text'];
}
