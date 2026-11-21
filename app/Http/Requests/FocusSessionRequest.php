<?php

namespace App\Http\Requests;

class FocusSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'day' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
