<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Base for API writes. The wire format is camelCase; the database is
 * snake_case. `attributesForModel()` is the single place that crosses over.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validated input with keys converted to snake_case column names.
     *
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $attributes = [];
        foreach ($this->validated() as $key => $value) {
            $attributes[Str::snake($key)] = $value;
        }

        return $attributes;
    }
}
