<?php

namespace App\Http\Controllers;

use App\Models\SkillLevel;
use App\Models\SkillPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A member's own skill paths: the skills they choose to build, with notes,
 * drawn with the same tree designs as the administrators' paths. Skills can be
 * ticked off in any order.
 */
class MySkillController extends Controller
{
    private const MAX_PATHS = 30;

    private const MAX_LEVELS = 40;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['paths' => $this->mine($request)]);
    }

    public function savePath(Request $request, ?SkillPath $path = null): JsonResponse
    {
        $path && $this->own($request, $path);
        $data = $request->validate([
            'title' => [$path ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        if (! $path) {
            abort_if(SkillPath::where('user_id', $request->user()->id)->count() >= self::MAX_PATHS, 422, __('validation.custom.skills.tooMany'));
            $path = new SkillPath(['is_active' => true]);
            $path->user_id = $request->user()->id;
        }
        if (array_key_exists('title', $data)) {
            $path->title = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $path->description = $data['description'] ?? '';
        }
        $path->save();

        return response()->json(['paths' => $this->mine($request)], $path->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyPath(Request $request, SkillPath $path): JsonResponse
    {
        $this->own($request, $path);
        DB::table('skill_progress')->whereIn('skill_level_id', $path->levels()->select('id'))->delete();
        $path->levels()->delete();
        $path->delete();

        return response()->json(['paths' => $this->mine($request)]);
    }

    public function saveLevel(Request $request, SkillPath $path, ?SkillLevel $level = null): JsonResponse
    {
        $this->own($request, $path);
        abort_if($level && $level->skill_path_id !== $path->id, 404);
        $data = $request->validate([
            'title' => [$level ? 'sometimes' : 'required', 'string', 'max:120'],
            // The member's notes on the skill: what it is, resources, how they practise.
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        if (! $level) {
            abort_if($path->levels()->count() >= self::MAX_LEVELS, 422, __('validation.custom.skills.tooMany'));
            $level = new SkillLevel(['position' => (int) $path->levels()->max('position') + 1]);
            $level->skill_path_id = $path->id;
        }
        if (array_key_exists('title', $data)) {
            $level->title = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $level->description = $data['description'] ?? '';
        }
        $level->save();

        return response()->json(['paths' => $this->mine($request)], $level->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyLevel(Request $request, SkillPath $path, SkillLevel $level): JsonResponse
    {
        $this->own($request, $path);
        abort_if($level->skill_path_id !== $path->id, 404);
        DB::table('skill_progress')->where('skill_level_id', $level->id)->delete();
        $level->delete();

        return response()->json(['paths' => $this->mine($request)]);
    }

    /** Tick a skill off, or untick it. */
    public function toggle(Request $request, SkillPath $path, SkillLevel $level): JsonResponse
    {
        $this->own($request, $path);
        abort_if($level->skill_path_id !== $path->id, 404);
        $row = DB::table('skill_progress')->where('user_id', $request->user()->id)->where('skill_level_id', $level->id);
        if ($row->exists()) {
            $row->delete();
        } else {
            DB::table('skill_progress')->insert(['user_id' => $request->user()->id, 'skill_level_id' => $level->id, 'passed_at' => now()]);
        }

        return response()->json(['paths' => $this->mine($request)]);
    }

    private function own(Request $request, SkillPath $path): void
    {
        abort_unless($path->user_id === $request->user()->id, 404);
    }

    /** @return list<array<string, mixed>> */
    private function mine(Request $request): array
    {
        $passed = DB::table('skill_progress')->where('user_id', $request->user()->id)->pluck('passed_at', 'skill_level_id');

        return SkillPath::where('user_id', $request->user()->id)->with('levels')->orderBy('id')->get()
            ->map(fn (SkillPath $path): array => [
                'id' => $path->id, 'title' => $path->title, 'description' => $path->description, 'isActive' => true, 'own' => true,
                'levels' => $path->levels->map(fn (SkillLevel $level): array => [
                    'id' => $level->id, 'title' => $level->title, 'description' => $level->description, 'challenge' => '', 'lessonId' => null,
                    'quiz' => [], 'passed' => $passed->has($level->id), 'unlocked' => true,
                ])->all(),
            ])->all();
    }
}
