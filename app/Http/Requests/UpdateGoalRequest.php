<?php

namespace App\Http\Requests;

class UpdateGoalRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('goal'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:180'],
            'targetValue' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            // Clamped to 0..target by the model; only the type is checked here.
            'currentValue' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
