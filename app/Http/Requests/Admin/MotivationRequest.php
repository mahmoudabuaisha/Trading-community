<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Motivation;
use Illuminate\Validation\Rule;

class MotivationRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('area', 'growth');
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:180'],
            'body' => [$required, 'string', 'max:10000'],
            'category' => [$required, Rule::in(Motivation::CATEGORIES)],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
