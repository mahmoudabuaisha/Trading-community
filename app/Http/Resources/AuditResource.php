<?php

namespace App\Http\Resources;

use App\Models\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdminAudit */
class AuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'adminName' => $this->admin?->display_name ?? '',
            'targetUserId' => $this->target_user_id,
            'details' => $this->details,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
