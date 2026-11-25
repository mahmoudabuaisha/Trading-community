<?php

namespace App\Http\Resources;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Badge */
class BadgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->achievementKey(),
            'title' => $this->tr('title'),
            'description' => $this->tr('description'),
            'points' => $this->points,
            'period' => $this->period,
            'conditions' => $this->conditions ?? [],
            'isActive' => $this->is_active,
        ];
    }
}
