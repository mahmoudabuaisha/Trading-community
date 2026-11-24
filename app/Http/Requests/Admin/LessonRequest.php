<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

/** Sent as multipart form data so the lesson's image can travel with it. */
class LessonRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    protected function prepareForValidation(): void
    {
        // Multipart fields arrive as strings; normalise the booleans.
        foreach (['isPublished', 'removeImage'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
            }
        }
    }

    public function rules(): array
    {
        $required = $this->route('lesson') ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:180'],
            'body' => [$required, 'string', 'max:20000'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'isPublished' => ['sometimes', 'boolean'],
            'removeImage' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
