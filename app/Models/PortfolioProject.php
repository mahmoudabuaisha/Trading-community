<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PortfolioProject extends Model
{
    public const IMAGE_DIRECTORY = 'portfolio';

    public const CATEGORIES = ['trading', 'business', 'design', 'programming', 'writing', 'marketing', 'study', 'other'];

    /** Who can open a member's portfolio page. */
    public const VISIBILITIES = ['private', 'members', 'public'];

    protected $fillable = ['title', 'description', 'category', 'skills', 'link', 'completed_on'];

    protected $attributes = ['description' => '', 'category' => 'other'];

    protected function casts(): array
    {
        return ['skills' => 'array', 'completed_on' => 'date:Y-m-d'];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'skills' => $this->skills ?? [],
            'link' => $this->link,
            'imageUrl' => $this->image_path ? '/media/'.$this->image_path : null,
            'completedOn' => $this->completed_on?->toDateString(),
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (PortfolioProject $project): void {
            if ($project->image_path) {
                Storage::disk('public')->delete($project->image_path);
            }
        });
    }
}
