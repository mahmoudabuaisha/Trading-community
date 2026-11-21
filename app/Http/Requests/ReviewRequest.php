<?php

namespace App\Http\Requests;

class ReviewRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        // The day comes from the URL; validate it as a real calendar date.
        $this->merge(['date' => $this->route('date')]);
    }

    public function rules(): array
    {
        $text = ['present', 'nullable', 'string', 'max:2000'];

        return ['date' => ['required', 'date_format:Y-m-d'], 'win' => $text, 'unfinished' => $text, 'lesson' => $text, 'tomorrow' => $text];
    }
}
