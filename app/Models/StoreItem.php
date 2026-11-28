<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StoreItem extends Model
{
    use Translatable;

    /** Fields members read in their own language. */
    public const TRANSLATABLE = ['name', 'description', 'value'];

    public const IMAGE_DIRECTORY = 'store';

    /** title: text under the name · product: anything else, in a section the administrators made · frame: ring around the photo · theme: colours of the app · avatar: the item's image as profile photo · emblem: a small badge next to the name. */
    public const KINDS = ['title', 'frame', 'theme', 'avatar', 'product', 'emblem'];

    /** Files a product hands over live on the private disk and are served only to their buyers. */
    public const FILE_DIRECTORY = 'store-files';

    /** Built-in frame styles and themes; their looks live in the front end's CSS. */
    public const FRAMES = ['bronze', 'silver', 'gold', 'royal', 'elite', 'legend'];

    /** Illustrated title looks (icon and colour); their pictures live in public/images/titles. */
    public const TITLE_STYLES = ['committed', 'persistent', 'achiever', 'ambitious', 'creative', 'excellent', 'challenger', 'professional', 'wise', 'strategist', 'leader', 'legend'];

    /** Who a profile picture is meant for; null is everyone. */
    public const AUDIENCES = ['male', 'female'];

    /** Theme pictures replace the mountain behind the pages. */
    public const THEME_DIRECTORY = 'themes';

    protected $fillable = ['name', 'description', 'kind', 'value', 'price', 'is_active', 'category_id', 'link', 'is_exclusive', 'theme_accent', 'theme_background', 'audience', 'style'];

    protected $attributes = ['description' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_active' => 'boolean', 'is_exclusive' => 'boolean'];
    }

    /** What the front end needs to dress the app in this theme. */
    public function themeData(): ?array
    {
        if ($this->kind !== 'theme' || ! $this->theme_accent) {
            return null;
        }

        return [
            'id' => $this->id,
            'name' => $this->tr('name'),
            'accent' => $this->theme_accent,
            'background' => $this->theme_background,
            'imageUrl' => $this->theme_image_path ? '/media/'.$this->theme_image_path : null,
        ];
    }

    /**
     * What a worn frame or emblem draws: a built-in frame style or the uploaded
     * picture for a frame; the uploaded icon or an emoji for an emblem.
     */
    public function look(): ?string
    {
        return match ($this->kind) {
            'frame' => $this->value === 'image' ? $this->imageUrl() : $this->value,
            'emblem' => $this->imageUrl() ?? ($this->value ?: null),
            default => $this->value,
        };
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path && str_starts_with($this->image_path, 'builtin:')) {
            // Pictures that ship with the platform, served straight from public/images.
            return '/images/'.substr($this->image_path, 8);
        }

        return $this->image_path ? '/media/'.$this->image_path : null;
    }

    protected static function booted(): void
    {
        static::deleted(function (StoreItem $item): void {
            SubscriptionGrant::forget('store_item', $item->id);
            if ($item->image_path && ! str_starts_with($item->image_path, 'builtin:')) {
                Storage::disk('public')->delete($item->image_path);
            }
            if ($item->theme_image_path) {
                Storage::disk('public')->delete($item->theme_image_path);
            }
            if ($item->file_path) {
                Storage::disk('local')->delete($item->file_path);
            }
        });
    }

    /** `value` is language only when the item is a title; otherwise it is a colour or a code. */
    public function translatableFields(): array
    {
        return $this->kind === 'title' ? self::TRANSLATABLE : ['name', 'description'];
    }
}
