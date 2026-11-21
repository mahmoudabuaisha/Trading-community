<?php

namespace App\Http\Resources;

use App\Models\DailyReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DailyReview */
class DailyReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'win' => $this->win,
            'unfinished' => $this->unfinished,
            'lesson' => $this->lesson,
            'tomorrow' => $this->tomorrow,
            'savedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
