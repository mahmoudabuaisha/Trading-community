<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Badge;
use Illuminate\Validation\Rule;

class BadgeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'metric' => [$required, Rule::in(Badge::METRICS)],
            'threshold' => [$required, 'integer', 'min:1', 'max:1000000'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    public function attributesForModel(): array
    {
        $attributes = parent::attributesForModel();
        if (array_key_exists('description', $attributes)) {
            $attributes['description'] ??= '';
        }

        return $attributes;
    }
}
