<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Sections of the store the administrators add themselves, such as "Books". */
class StoreCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return self::all();
    }

    public function store(Request $request, AdminService $admin): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        StoreCategory::create(['name' => trim($data['name']), 'position' => (int) StoreCategory::max('position') + 1]);
        $admin->record($request->user(), 'store_category_created', $data['name']);

        return self::all(201);
    }

    public function update(Request $request, StoreCategory $category, AdminService $admin): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $category->update(['name' => trim($data['name'])]);
        $admin->record($request->user(), 'store_category_updated', $category->name);

        return self::all();
    }

    /** The section goes; its items stay in the store, without a section. */
    public function destroy(Request $request, StoreCategory $category, AdminService $admin): Response
    {
        $category->delete();
        $admin->record($request->user(), 'store_category_deleted', $category->name);

        return response()->noContent();
    }

    private static function all(int $status = 200): JsonResponse
    {
        return response()->json(['categories' => StoreCategory::orderBy('position')->orderBy('id')->get()->map(fn (StoreCategory $category): array => ['id' => $category->id, 'name' => $category->name])], $status);
    }
}
