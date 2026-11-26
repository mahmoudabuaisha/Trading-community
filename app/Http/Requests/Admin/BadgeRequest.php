<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Badge;
use Illuminate\Validation\Rule;

class BadgeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('area', 'badges');
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'points' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'period' => ['sometimes', Rule::in(Badge::PERIODS)],
            // Every condition must hold within the period for the badge to be earned.
            'conditions' => [$required, 'array', 'min:1', 'max:5'],
            'conditions.*.metric' => ['required', 'distinct', Rule::in(Badge::METRICS)],
            'conditions.*.threshold' => ['required', 'integer', 'min:1', 'max:1000000'],
            'isActive' => ['sometimes', 'boolean'],
            // A store item handed over free with the achievement.
            'rewardItemId' => ['sometimes', 'nullable', 'integer', 'exists:store_items,id'],
        ];
    }

    public function attributesForModel(): array
    {
        $attributes = parent::attributesForModel();
        if (array_key_exists('description', $attributes)) {
            $attributes['description'] ??= '';
        }
        if (isset($attributes['conditions'])) {
            $attributes['conditions'] = array_map(
                fn (array $condition): array => ['metric' => $condition['metric'], 'threshold' => (int) $condition['threshold']],
                array_values($attributes['conditions']),
            );
        }

        return $attributes;
    }
}
