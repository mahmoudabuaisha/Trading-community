<?php

namespace App\Http\Resources;

use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Achievement */
class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'key' => $this->key, 'earnedAt' => $this->earned_at->toISOString()];
    }
}
