<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->display_name,
            'email' => $this->email,
            'role' => $this->role,
            'isPremium' => (bool) $this->is_premium,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
