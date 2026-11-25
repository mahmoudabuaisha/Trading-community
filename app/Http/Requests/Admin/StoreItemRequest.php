<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\StoreItem;
use Illuminate\Validation\Rule;

class StoreItemRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('area', 'store');
    }

    protected function prepareForValidation(): void
    {
        // Multipart fields arrive as strings; normalise the booleans.
        foreach (['isActive', 'removeImage'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
            }
        }
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $required = $item ? 'sometimes' : 'required';
        $kind = $this->input('kind', $item?->kind);
        // What `value` means depends on the kind of item.
        $value = match ($kind) {
            'title' => ['required', 'string', 'max:40'],
            'frame' => ['required', Rule::in(StoreItem::FRAMES)],
            'theme' => ['required', Rule::in(StoreItem::THEMES)],
            default => ['nullable'],
        };
        $valueNeeded = ! $item || $this->has('kind') || $this->has('value');
        // An avatar item is its picture, so it cannot exist without one.
        $imageNeeded = $kind === 'avatar' && (! $item?->image_path || $this->boolean('removeImage'));

        return [
            'name' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'kind' => [$required, Rule::in(StoreItem::KINDS)],
            'value' => $valueNeeded ? $value : ['sometimes'],
            'price' => [$required, 'integer', 'min:0', 'max:1000000'],
            'isActive' => ['sometimes', 'boolean'],
            'removeImage' => ['sometimes', 'boolean'],
            'image' => [$imageNeeded ? 'required' : 'sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
