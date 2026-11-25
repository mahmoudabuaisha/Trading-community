<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends ApiRequest
{
    public function authorize(): bool
    {
        // Editing accounts is the "users" area; changing who is staff, and with which
        // permissions, stays with full administrators.
        if ($this->hasAny(['role', 'permissions'])) {
            return $this->user()->can('admin');
        }

        return $this->user()->can('area', 'users');
    }

    public function rules(): array
    {
        return [
            'displayName' => ['sometimes', 'string', 'min:1', 'max:120'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'isPremium' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(User::PERMISSIONS)],
        ];
    }
}
