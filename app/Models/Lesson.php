<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    use Translatable;

    /** Fields members read in their own language. */
    public const TRANSLATABLE = ['title', 'body'];

    public const IMAGE_DIRECTORY = 'lessons';

    protected $fillable = ['title', 'body', 'image_path', 'position', 'is_published', 'is_premium'];

    protected $attributes = ['is_published' => true, 'position' => 0];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_premium' => 'boolean', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        // The image file belongs to the lesson: remove it when the lesson goes
        // or when a different image replaces it, whichever code path does it.
        static::updated(function (Lesson $lesson): void {
            $previous = $lesson->getOriginal('image_path');
            if ($previous && $previous !== $lesson->image_path) {
                Storage::disk('public')->delete($previous);
            }
        });
        static::deleted(function (Lesson $lesson): void {
            SubscriptionGrant::forget('lesson', $lesson->id);
            if ($lesson->image_path) {
                Storage::disk('public')->delete($lesson->image_path);
            }
        });
    }
}
