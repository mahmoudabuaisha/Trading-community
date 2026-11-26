<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A look for the goal map or the skill tree. The layout is one of the shapes
 * built into the app; the administrators dress it (colour, node shape,
 * background), name it and price it.
 */
class Design extends Model
{
    use Translatable;

    public const TRANSLATABLE = ['name', 'description'];

    public const IMAGE_DIRECTORY = 'designs';

    public const TARGETS = ['goal_map', 'skill_tree'];

    /** The shapes each target can take; drawing them is the front end's job. */
    public const LAYOUTS = [
        'goal_map' => ['mindmap', 'branches', 'orbit'],
        'skill_tree' => ['tree', 'pyramid', 'stairs'],
    ];

    public const NODE_SHAPES = ['circle', 'rounded', 'hexagon', 'diamond', 'star', 'apple'];

    protected $fillable = ['target', 'layout', 'name', 'description', 'price', 'accent', 'node_shape', 'position', 'is_active'];

    protected $attributes = ['description' => '', 'price' => 0, 'accent' => '#d6ad62', 'node_shape' => 'circle', 'is_active' => true];

    protected function casts(): array
    {
        return ['price' => 'integer', 'position' => 'integer', 'is_active' => 'boolean'];
    }

    public function toApi(bool $admin = false): array
    {
        return [
            'id' => $this->id,
            'target' => $this->target,
            'layout' => $this->layout,
            'name' => $admin ? $this->name : $this->tr('name'),
            'description' => $admin ? $this->description : $this->tr('description'),
            'price' => $this->price,
            'accent' => $this->accent,
            'nodeShape' => $this->node_shape,
            'backgroundUrl' => $this->background_path ? '/media/'.$this->background_path : null,
            'isActive' => $this->is_active,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Design $design): void {
            if ($design->background_path) {
                Storage::disk('public')->delete($design->background_path);
            }
        });
    }
}
