<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreItemRequest;
use App\Http\Resources\StoreItemResource;
use App\Models\StoreItem;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class StoreItemController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('area', 'store');

        return response()->json(['items' => StoreItemResource::collection(StoreItem::orderBy('price')->orderBy('id')->get())]);
    }

    public function store(StoreItemRequest $request, AdminService $admin): JsonResponse
    {
        return response()->json(['item' => new StoreItemResource($this->save($request, $admin, null)->refresh())], 201);
    }

    public function update(StoreItemRequest $request, StoreItem $item, AdminService $admin): JsonResponse
    {
        return response()->json(['item' => new StoreItemResource($this->save($request, $admin, $item))]);
    }

    public function destroy(Request $request, StoreItem $item, AdminService $admin): Response
    {
        Gate::authorize('area', 'store');
        $admin->deleteStoreItem($request->user(), $item);

        return response()->noContent();
    }

    private function save(StoreItemRequest $request, AdminService $admin, ?StoreItem $item): StoreItem
    {
        $attributes = Arr::except($request->attributesForModel(), ['image', 'remove_image', 'file', 'remove_file']);
        if (array_key_exists('description', $attributes)) {
            $attributes['description'] ??= '';
        }

        return $admin->saveStoreItem($request->user(), $item, $attributes, $request->file('image'), (bool) $request->validated('removeImage'), $request->file('file'), (bool) $request->validated('removeFile'));
    }
}
