<?php

namespace App\Http\Controllers;

use App\Models\SkillLevel;
use App\Models\SkillPath;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Skill paths: members climb them level by level; administrators write them. */
class SkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['paths' => $this->paths($request, SkillPath::where('is_active', true))]);
    }

    /**
     * Try to pass a level. With a quiz, `answers` holds the chosen option of
     * each question; without one, the member confirms the challenge is done.
     */
    public function attempt(Request $request, SkillLevel $level): JsonResponse
    {
        $user = $request->user();
        $path = $level->path;
        abort_unless($path->is_active, 404);
        $passed = DB::table('skill_progress')->where('user_id', $user->id)->pluck('skill_level_id');
        // Levels open in order: every earlier level of the path must be passed.
        $earlier = $path->levels->takeWhile(fn (SkillLevel $other): bool => $other->id !== $level->id);
        abort_if($earlier->contains(fn (SkillLevel $other): bool => ! $passed->contains($other->id)), 422, __('validation.custom.skills.locked'));

        $quiz = $level->quiz ?? [];
        $score = 100;
        if ($quiz) {
            $answers = $request->validate(['answers' => ['required', 'array', 'size:'.count($quiz)], 'answers.*' => ['required', 'integer', 'min:0']])['answers'];
            $right = collect($quiz)->filter(fn (array $question, int $index): bool => (int) $answers[$index] === (int) $question['answer'])->count();
            $score = (int) round($right * 100 / count($quiz));
        }
        $ok = $score >= SkillLevel::PASS_PERCENT;
        if ($ok) {
            DB::table('skill_progress')->insertOrIgnore(['user_id' => $user->id, 'skill_level_id' => $level->id, 'passed_at' => now()]);
        }

        return response()->json(['passed' => $ok, 'score' => $score, 'needed' => SkillLevel::PASS_PERCENT, 'paths' => $this->paths($request, SkillPath::where('is_active', true))]);
    }

    // ── Administration (the "education" area) ────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        Gate::authorize('area', 'education');

        return response()->json(['paths' => $this->paths($request, SkillPath::query(), withAnswers: true)]);
    }

    public function savePath(Request $request, AdminService $admin, ?SkillPath $path = null): JsonResponse
    {
        Gate::authorize('area', 'education');
        $data = $request->validate([
            'title' => [$path ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
        $creating = $path === null;
        $path ??= new SkillPath;
        $path->fill(array_filter(['title' => $data['title'] ?? null, 'is_active' => $data['isActive'] ?? null], fn ($value): bool => $value !== null));
        if (array_key_exists('description', $data)) {
            $path->description = $data['description'] ?? '';
        }
        if ($creating) {
            $path->created_by = $request->user()->id;
        }
        $path->save();
        $admin->record($request->user(), $creating ? 'skill_path_created' : 'skill_path_updated', $path->title);

        return response()->json(['paths' => $this->paths($request, SkillPath::query(), withAnswers: true)], $creating ? 201 : 200);
    }

    public function destroyPath(Request $request, SkillPath $path, AdminService $admin): Response
    {
        Gate::authorize('area', 'education');
        $path->delete();
        $admin->record($request->user(), 'skill_path_deleted', $path->title);

        return response()->noContent();
    }

    public function saveLevel(Request $request, SkillPath $path, ?SkillLevel $level = null): JsonResponse
    {
        Gate::authorize('area', 'education');
        abort_if($level && $level->skill_path_id !== $path->id, 404);
        $data = $request->validate([
            'title' => [$level ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'challenge' => ['sometimes', 'nullable', 'string', 'max:500'],
            'lessonId' => ['sometimes', 'nullable', 'integer', 'exists:lessons,id'],
            'quiz' => ['sometimes', 'array', 'max:20'],
            'quiz.*.question' => ['required', 'string', 'max:300'],
            'quiz.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'quiz.*.options.*' => ['required', 'string', 'max:200'],
            'quiz.*.answer' => ['required', 'integer', 'min:0'],
            'move' => ['sometimes', 'in:up,down'],
        ]);
        foreach ($data['quiz'] ?? [] as $index => $question) {
            abort_if($question['answer'] >= count($question['options']), 422, __('validation.custom.skills.answer', ['n' => $index + 1]));
        }
        $level ??= $path->levels()->make(['position' => (int) $path->levels()->max('position') + 1]);
        $level->fill(array_filter(['title' => $data['title'] ?? null], fn ($value): bool => $value !== null));
        foreach (['description' => 'description', 'challenge' => 'challenge'] as $key => $column) {
            if (array_key_exists($key, $data)) {
                $level->{$column} = $data[$key] ?? '';
            }
        }
        if (array_key_exists('lessonId', $data)) {
            $level->lesson_id = $data['lessonId'];
        }
        if (array_key_exists('quiz', $data)) {
            $level->quiz = array_values($data['quiz']) ?: null;
        }
        if (isset($data['move']) && $level->exists) {
            $siblings = $path->levels()->get();
            $other = $siblings->get($siblings->search(fn (SkillLevel $item): bool => $item->id === $level->id) + ($data['move'] === 'up' ? -1 : 1));
            if ($other) {
                [$level->position, $other->position] = [$other->position, $level->position];
                $other->save();
            }
        }
        $level->save();

        return response()->json(['paths' => $this->paths($request, SkillPath::query(), withAnswers: true)]);
    }

    public function destroyLevel(SkillPath $path, SkillLevel $level): Response
    {
        Gate::authorize('area', 'education');
        abort_if($level->skill_path_id !== $path->id, 404);
        $level->delete();

        return response()->noContent();
    }

    /** Paths with their levels and the caller's progress. Correct answers leave the server only for administrators. */
    private function paths(Request $request, $query, bool $withAnswers = false): array
    {
        $passed = DB::table('skill_progress')->where('user_id', $request->user()->id)->pluck('passed_at', 'skill_level_id');

        return $query->with('levels')->orderBy('id')->get()->map(function (SkillPath $path) use ($passed, $withAnswers): array {
            $open = true;

            return [
                'id' => $path->id, 'title' => $path->tr('title'), 'description' => $path->tr('description'), 'isActive' => $path->is_active,
                'levels' => $path->levels->map(function (SkillLevel $level) use ($passed, $withAnswers, &$open): array {
                    $done = $passed->has($level->id);
                    $row = [
                        'id' => $level->id, 'title' => $level->tr('title'), 'description' => $level->tr('description'), 'challenge' => $level->tr('challenge'), 'lessonId' => $level->lesson_id,
                        'quiz' => collect($level->quiz ?? [])->map(fn (array $question): array => $withAnswers ? $question : ['question' => $question['question'], 'options' => $question['options']])->all(),
                        'passed' => $done,
                        // The first level not yet passed is the one open to attempt.
                        'unlocked' => $done || $open,
                    ];
                    $open = $open && $done;

                    return $row;
                })->all(),
            ];
        })->all();
    }
}
