<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StoreItem extends Model
{
    public const IMAGE_DIRECTORY = 'store';

    /** title: text under the name · frame: ring around the photo · theme: colours of the app · avatar: the item's image as profile photo. */
    public const KINDS = ['title', 'frame', 'theme', 'avatar'];

    /** Built-in frame styles and themes; their looks live in the front end's CSS. */
    public const FRAMES = ['bronze', 'silver', 'gold', 'royal', 'diamond'];

    public const THEMES = ['emerald', 'sapphire', 'ruby', 'amethyst', 'silver'];

    protected $fillable = ['name', 'description', 'kind', 'value', 'price', 'is_active'];

    protected $attributes = ['description' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_active' => 'boolean'];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? '/media/'.$this->image_path : null;
    }

    protected static function booted(): void
    {
        static::deleted(function (StoreItem $item): void {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
        });
    }
}
