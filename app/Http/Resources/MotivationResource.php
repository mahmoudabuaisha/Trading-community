<?php

namespace App\Http\Resources;

use App\Models\Motivation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Motivation */
class MotivationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->tr('title'),
            'body' => $this->tr('body'),
            'category' => $this->category,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
