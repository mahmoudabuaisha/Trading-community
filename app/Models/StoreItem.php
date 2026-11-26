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

    /** title: text under the name · product: anything else, in a section the administrators made · frame: ring around the photo · theme: colours of the app · avatar: the item's image as profile photo. */
    public const KINDS = ['title', 'frame', 'theme', 'avatar', 'product'];

    /** Files a product hands over live on the private disk and are served only to their buyers. */
    public const FILE_DIRECTORY = 'store-files';

    /** Built-in frame styles and themes; their looks live in the front end's CSS. */
    public const FRAMES = ['bronze', 'silver', 'gold', 'royal', 'diamond'];

    public const THEMES = ['emerald', 'sapphire', 'ruby', 'amethyst', 'silver'];

    protected $fillable = ['name', 'description', 'kind', 'value', 'price', 'is_active', 'category_id', 'link', 'is_exclusive'];

    protected $attributes = ['description' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_active' => 'boolean', 'is_exclusive' => 'boolean'];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? '/media/'.$this->image_path : null;
    }

    protected static function booted(): void
    {
        static::deleted(function (StoreItem $item): void {
            SubscriptionGrant::forget('store_item', $item->id);
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            if ($item->file_path) {
                Storage::disk('local')->delete($item->file_path);
            }
        });
    }
}
