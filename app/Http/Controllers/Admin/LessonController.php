<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('area', 'education');

        return response()->json(['lessons' => LessonResource::collection(Lesson::orderBy('position')->orderBy('id')->get())]);
    }

    public function store(LessonRequest $request, AdminService $admin): JsonResponse
    {
        $lesson = $this->save($request, $admin, null);

        return response()->json(['lesson' => new LessonResource($lesson->refresh())], 201);
    }

    public function update(LessonRequest $request, Lesson $lesson, AdminService $admin): JsonResponse
    {
        return response()->json(['lesson' => new LessonResource($this->save($request, $admin, $lesson))]);
    }

    public function destroy(Request $request, Lesson $lesson, AdminService $admin): Response
    {
        Gate::authorize('area', 'education');
        $admin->deleteLesson($request->user(), $lesson);

        return response()->noContent();
    }

    private function save(LessonRequest $request, AdminService $admin, ?Lesson $lesson): Lesson
    {
        $attributes = Arr::except($request->attributesForModel(), ['image', 'remove_image']);

        return $admin->saveLesson($request->user(), $lesson, $attributes, $request->file('image'), (bool) $request->validated('removeImage'));
    }
}
