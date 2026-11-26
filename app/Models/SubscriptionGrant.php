<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One paid service an administrator opened for one member. */
class SubscriptionGrant extends Model
{
    /** What can be opened: subscriber-only lessons, books, and store items reserved to subscribers. */
    public const TYPES = ['lesson', 'book', 'store_item', 'assistant'];

    protected $fillable = ['grantable_type', 'grantable_id'];

    /** Drop every grant pointing at a deleted lesson, book or store item. */
    public static function forget(string $type, int $id): void
    {
        self::where('grantable_type', $type)->where('grantable_id', $id)->delete();
    }
}
