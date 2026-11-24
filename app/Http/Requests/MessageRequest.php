<?php

namespace App\Http\Requests;

class MessageRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:4000']];
    }
}
