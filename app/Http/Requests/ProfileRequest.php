<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class ProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:120'],
            'surname' => ['present', 'nullable', 'string', 'max:120'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['present', 'nullable', 'string', 'max:500'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'language' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'weeklyGoal' => ['required', 'integer', 'min:1', 'max:500'],
            // Optional: null means the member prefers not to say.
            'gender' => ['sometimes', 'nullable', Rule::in(User::GENDERS)],
            'specialties' => ['sometimes', 'array', 'max:4'],
            'specialties.*' => ['string', 'distinct', Rule::in(User::SPECIALTIES)],
            'dailyFocusMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            // Only an inline image data URL is accepted — never a remote URL or script.
            'avatarUrl' => ['present', 'nullable', 'string', 'max:520000', 'regex:/^(data:image\/(png|jpeg|webp|gif);base64,[A-Za-z0-9+\/=]+|\/media\/store\/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)|\/images\/avatars\/[a-z]+\.webp)$/'],
        ];
    }

    public function attributesForModel(): array
    {
        $attributes = parent::attributesForModel();
        $attributes['surname'] ??= '';
        $attributes['bio'] ??= '';
        if (array_key_exists('nickname', $attributes)) {
            $attributes['nickname'] ??= '';
        }

        return $attributes;
    }
}
