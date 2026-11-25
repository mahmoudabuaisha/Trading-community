<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VisionItem extends Model
{
    public const IMAGE_DIRECTORY = 'vision';

    public const KINDS = ['image', 'quote', 'dream'];

    public const SIZES = ['sm', 'md', 'lg'];

    protected $fillable = ['kind', 'text', 'goal_id', 'size', 'position'];

    protected $attributes = ['text' => '', 'size' => 'md'];

    protected static function booted(): void
    {
        static::deleted(function (VisionItem $item): void {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
        });
    }
}
