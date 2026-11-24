<?php

namespace App\Http\Requests;

class HabitRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:80'],
            'targetDays' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
