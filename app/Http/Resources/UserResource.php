<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'displayName' => $this->display_name,
            'surname' => $this->surname,
            'nickname' => $this->nickname,
            'isPremium' => (bool) $this->is_premium,
            'avatarUrl' => $this->avatar_url,
            'role' => $this->role,
            'bio' => $this->bio,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'weeklyGoal' => $this->weekly_goal,
            'dailyFocusMinutes' => $this->daily_focus_minutes,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
