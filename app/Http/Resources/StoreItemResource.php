<?php

namespace App\Http\Resources;

use App\Models\StoreItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoreItem */
class StoreItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind,
            'value' => $this->value,
            'imageUrl' => $this->imageUrl(),
            'price' => $this->price,
            'isActive' => $this->is_active,
            // Present on the member's store; absent in the admin list.
            'owned' => $this->when(isset($this->owned), fn () => (bool) $this->owned),
            'equipped' => $this->when(isset($this->equipped), fn () => (bool) $this->equipped),
        ];
    }
}
