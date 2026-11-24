<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class SettingsRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        // An empty value clears the override and restores the built-in text.
        $text = ['present', 'nullable', 'string', 'max:160'];

        return ['taglineAr' => $text, 'taglineEn' => $text, 'welcomeSubAr' => $text, 'welcomeSubEn' => $text];
    }
}
