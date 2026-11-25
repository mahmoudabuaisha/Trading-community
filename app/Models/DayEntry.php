<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A picture and a note a member pinned to one calendar day. */
class DayEntry extends Model
{
    public const IMAGE_DIRECTORY = 'days';

    protected $fillable = ['day', 'note'];

    protected $attributes = ['note' => ''];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    public function toApi(): array
    {
        return ['day' => $this->day->toDateString(), 'note' => $this->note, 'imageUrl' => $this->image_path ? '/media/'.$this->image_path : null];
    }

    protected static function booted(): void
    {
        static::deleted(function (DayEntry $entry): void {
            if ($entry->image_path) {
                Storage::disk('public')->delete($entry->image_path);
            }
        });
    }
}
