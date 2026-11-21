<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Validation\Rule;

/** Shared by create (all core fields required) and partial update (PATCH). */
class TaskRequest extends ApiRequest
{
    public function authorize(): bool
    {
        // Ownership is settled before validation, so a stranger's task answers
        // 403 rather than leaking validation details.
        $task = $this->route('task');

        return $task === null || $this->user()->can('update', $task);
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        // On a partial update the range is checked against the stored start day.
        $start = $this->input('periodStart') ?? $this->route('task')?->period_start?->toDateString();

        return [
            'title' => [$required, 'string', 'min:1', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'periodType' => [$required, Rule::in(Task::PERIOD_TYPES)],
            'periodStart' => [$required, 'date_format:Y-m-d'],
            'periodEnd' => [$required, 'date_format:Y-m-d', ...($start ? ["after_or_equal:$start"] : [])],
            'dueDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
            // A task may only be filed under one of the caller's own categories.
            'categoryId' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    public function attributesForModel(): array
    {
        $attributes = parent::attributesForModel();
        if (array_key_exists('description', $attributes)) {
            $attributes['description'] ??= '';
        }
        // A new start with no new end must not leave the range inverted.
        if (isset($attributes['period_start']) && ! isset($attributes['period_end'])) {
            $end = $this->route('task')?->period_end?->toDateString();
            if ($end !== null && $end < $attributes['period_start']) {
                $attributes['period_end'] = $attributes['period_start'];
            }
        }

        return $attributes;
    }
}
