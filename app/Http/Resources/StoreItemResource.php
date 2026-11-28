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
            'name' => $this->tr('name'),
            'description' => $this->tr('description'),
            'kind' => $this->kind,
            // Only a title's text is language; a frame or theme key is a machine value.
            'value' => $this->kind === 'title' ? $this->tr('value') : $this->value,
            'categoryId' => $this->category_id,
            'hasFile' => $this->file_path !== null,
            'fileName' => $this->file_name,
            // What was bought is handed over to its owner only (and shown to administrators).
            'link' => ! empty($this->owned) || $request->is('api/admin/*') ? $this->link : null,
            'imageUrl' => $this->imageUrl(),
            'audience' => $this->audience,
            'style' => $this->style,
            'price' => $this->price,
            'isActive' => $this->is_active,
            'isExclusive' => $this->is_exclusive,
            'themeAccent' => $this->theme_accent,
            'themeBackground' => $this->theme_background,
            'themeImageUrl' => $this->theme_image_path ? '/media/'.$this->theme_image_path : null,
            // On the member's store: whether this member may buy a reserved item.
            'allowed' => $this->when(isset($this->allowed), fn () => (bool) $this->allowed),
            // Present on the member's store; absent in the admin list.
            'owned' => $this->when(isset($this->owned), fn () => (bool) $this->owned),
            'equipped' => $this->when(isset($this->equipped), fn () => (bool) $this->equipped),
        ];
    }
}
