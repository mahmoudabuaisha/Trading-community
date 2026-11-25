<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisionBoard extends Model
{
    public const AREAS = ['personal', 'career', 'finance', 'health', 'family', 'learning', 'travel', 'other'];

    /** Accent of the board; the same palettes as the app's themes. */
    public const COLORS = ['gold', 'emerald', 'sapphire', 'ruby', 'amethyst', 'silver'];

    protected $fillable = ['title', 'area', 'color'];

    public function items(): HasMany
    {
        return $this->hasMany(VisionItem::class)->orderBy('position')->orderBy('id');
    }

    protected static function booted(): void
    {
        // Row deletes cascade in the database; the image files need a hand.
        static::deleting(fn (VisionBoard $board) => $board->items->each->delete());
    }
}
