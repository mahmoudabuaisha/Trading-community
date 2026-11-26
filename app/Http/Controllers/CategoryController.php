<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** A member's life areas ("Trading", "Work", …). Tasks filed under one count towards it. */
class CategoryController extends Controller
{
    public const MAX_PER_USER = 20;

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->categories()->count() >= self::MAX_PER_USER, 422, __('validation.custom.areas.limit', ['max' => self::MAX_PER_USER]));
        $category = $user->categories()->create($this->data($request, $user->id, null));

        return response()->json(['category' => new CategoryResource($category)], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        abort_unless($category->user_id === $request->user()->id, 404);
        $category->update($this->data($request, $category->user_id, $category->id));

        return response()->json(['category' => new CategoryResource($category)]);
    }

    /** The area goes; its tasks stay, without an area. */
    public function destroy(Request $request, Category $category): Response
    {
        abort_unless($category->user_id === $request->user()->id, 404);
        $category->delete();

        return response()->noContent();
    }

    private function data(Request $request, int $userId, ?int $ignore): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('categories', 'name')->where('user_id', $userId)->ignore($ignore)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }
}
