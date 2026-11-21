<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'periodType' => $this->period_type,
            'periodStart' => $this->period_start->toDateString(),
            'periodEnd' => $this->period_end->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'priority' => $this->priority,
            'status' => $this->status,
            'categoryId' => $this->category_id,
            'completedAt' => $this->completed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
