<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Designs are part of what the store sells, so they belong to the store area. */
class DesignController extends Controller
{
    public function index(): JsonResponse
    {
        return self::all();
    }

    public function store(Request $request, AdminService $admin): JsonResponse
    {
        $design = new Design;
        $design->created_by = $request->user()->id;
        $design->position = (int) Design::max('position') + 1;
        $this->fill($request, $design, 'required');
        $admin->record($request->user(), 'design_created', $design->name);

        return self::all(201);
    }

    /** POST, not PATCH: the update may carry a background picture as multipart form data. */
    public function update(Request $request, Design $design, AdminService $admin): JsonResponse
    {
        $this->fill($request, $design, 'sometimes');
        $admin->record($request->user(), 'design_updated', $design->name);

        return self::all();
    }

    /** Members who bought it get their points back (spending is the sum of purchases) and fall back to a free design. */
    public function destroy(Request $request, Design $design, AdminService $admin): Response
    {
        $design->delete();
        $admin->record($request->user(), 'design_deleted', $design->name);

        return response()->noContent();
    }

    private function fill(Request $request, Design $design, string $required): void
    {
        foreach (['isActive', 'removeBackground'] as $flag) {
            if ($request->has($flag)) {
                $request->merge([$flag => filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
        $target = $request->input('target', $design->target);
        $data = $request->validate([
            'target' => [$required, Rule::in(Design::TARGETS)],
            'layout' => [$design->exists && ! $request->has('target') ? 'sometimes' : 'required', Rule::in(Design::LAYOUTS[$target] ?? [])],
            'name' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'price' => [$required, 'integer', 'min:0', 'max:1000000'],
            'accent' => [$required, 'regex:/^#[0-9a-fA-F]{6}$/'],
            'nodeShape' => [$required, Rule::in(Design::NODE_SHAPES)],
            'isActive' => ['sometimes', 'boolean'],
            'background' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'removeBackground' => ['sometimes', 'boolean'],
        ]);
        $old = $design->background_path;
        foreach (['target' => 'target', 'layout' => 'layout', 'name' => 'name', 'price' => 'price', 'accent' => 'accent', 'nodeShape' => 'node_shape', 'isActive' => 'is_active'] as $key => $column) {
            if (array_key_exists($key, $data)) {
                $design->{$column} = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            }
        }
        if (array_key_exists('description', $data)) {
            $design->description = trim((string) $data['description']);
        }
        if ($request->file('background')) {
            $design->background_path = $request->file('background')->store(Design::IMAGE_DIRECTORY, 'public');
        } elseif ($data['removeBackground'] ?? false) {
            $design->background_path = null;
        }
        $design->save();
        if ($old && $old !== $design->background_path) {
            Storage::disk('public')->delete($old);
        }
    }

    private static function all(int $status = 200): JsonResponse
    {
        return response()->json([
            'designs' => Design::orderBy('target')->orderBy('position')->orderBy('id')->get()->map(fn (Design $design): array => $design->toApi(admin: true)),
            'layouts' => Design::LAYOUTS,
            'nodeShapes' => Design::NODE_SHAPES,
        ], $status);
    }
}
