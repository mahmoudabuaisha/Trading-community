<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        // No `role` rule on purpose: roles are never accepted from sign-up input.
        return [
            'displayName' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8), 'max:255'],
            // A field hidden from people: sign-up bots that fill every input are turned away.
            'website' => ['prohibited'],
        ];
    }
}
