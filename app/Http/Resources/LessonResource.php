<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lesson */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // A subscriber-only lesson is listed for everyone, but its text and
        // picture are sent only to readers who are allowed to open it.
        $locked = $this->is_premium && ! $request->user()?->canReadPremium();

        return [
            'id' => $this->id,
            'title' => $this->tr('title'),
            'body' => $locked ? '' : $this->tr('body'),
            'imageUrl' => $this->image_path && ! $locked ? '/media/'.$this->image_path : null,
            'position' => $this->position,
            'isPublished' => $this->is_published,
            'isPremium' => $this->is_premium,
            'locked' => $locked,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
