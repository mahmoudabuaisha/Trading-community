<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'periodType' => ['required', Rule::in(Task::PERIOD_TYPES)],
            'periodStart' => ['required', 'date_format:Y-m-d'],
            'periodEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:periodStart'],
            'targetValue' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function attributesForModel(): array
    {
        $attributes = parent::attributesForModel();
        $attributes['description'] = $attributes['description'] ?? '';

        return $attributes;
    }
}
