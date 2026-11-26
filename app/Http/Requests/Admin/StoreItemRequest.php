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
        foreach (['isActive', 'removeImage', 'removeFile', 'isExclusive', 'removeThemeImage'] as $flag) {
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
            // A built-in style, or `image` to use the uploaded picture as the frame.
            'frame' => ['required', Rule::in([...StoreItem::FRAMES, 'image'])],
            // An emoji; without one the uploaded icon is the emblem.
            'emblem' => ['nullable', 'string', 'max:16'],
            'theme' => ['nullable'],
            default => ['nullable'],
        };
        $valueNeeded = ! $item || $this->has('kind') || $this->has('value');
        // An avatar item is its picture, so it cannot exist without one.
        $noImage = ! $item?->image_path || $this->boolean('removeImage');
        $imageNeeded = $noImage && ! $this->hasFile('image') && match ($kind) {
            'avatar' => true,
            'frame' => $this->input('value', $item?->value) === 'image',
            'emblem' => trim((string) $this->input('value', $item?->value)) === '',
            default => false,
        };

        return [
            'name' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'kind' => [$required, Rule::in(StoreItem::KINDS)],
            'value' => $valueNeeded ? $value : ['sometimes'],
            'price' => [$required, 'integer', 'min:0', 'max:1000000'],
            'isActive' => ['sometimes', 'boolean'],
            // Reserved to the subscribers it is opened for.
            'isExclusive' => ['sometimes', 'boolean'],
            // A theme: the accent its shades are derived from, an optional dark base, an optional picture.
            'themeAccent' => [$kind === 'theme' && (! $item || $this->has('kind')) ? 'required' : 'sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'themeBackground' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'themeImage' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'removeThemeImage' => ['sometimes', 'boolean'],
            'removeImage' => ['sometimes', 'boolean'],
            // A product sits in a section and may hand over a link and/or a file once bought.
            'categoryId' => [$kind === 'product' && (! $item || $this->has('kind')) ? 'required' : 'sometimes', 'nullable', 'integer', 'exists:store_categories,id'],
            'link' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'file' => ['sometimes', 'file', 'mimes:pdf,epub,zip,jpg,jpeg,png,webp,mp3,mp4,docx,xlsx,pptx,txt', 'max:20480'],
            'removeFile' => ['sometimes', 'boolean'],
            'image' => [$imageNeeded ? 'required' : 'sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
