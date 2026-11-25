<?php

namespace App\Http\Controllers;

use App\Models\BadgeAward;
use App\Models\Goal;
use App\Models\GoalStep;
use App\Models\PortfolioProject;
use App\Models\StorePurchase;
use App\Models\Task;
use App\Models\TimelineEntry;
use App\Services\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Performance DNA, the growth timeline, goal roadmaps and the data export. */
class JourneyController extends Controller
{
    public function insights(Request $request, InsightsService $insights): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $user = $request->user();

        return response()->json($insights->analyse($user, $request->query('date', now($user->timezone)->toDateString())));
    }

    /**
     * The timeline is derived from what happened (nothing is stored twice):
     * milestones, month-by-month stages, and the member's own notes and entries.
     */
    public function timeline(Request $request): JsonResponse
    {
        $user = $request->user();
        $local = fn ($instant): string => CarbonImmutable::parse($instant)->setTimezone($user->timezone)->toDateString();
        $events = collect([['key' => 'joined', 'kind' => 'joined', 'day' => $local($user->created_at), 'title' => null]]);

        foreach ($user->achievements()->get() as $achievement) {
            $events->push(['key' => 'achievement:'.$achievement->key, 'kind' => 'achievement', 'day' => $local($achievement->earned_at), 'title' => null, 'ref' => $achievement->key]);
        }
        // An admin-defined badge is a milestone the first time it is earned.
        foreach (BadgeAward::where('user_id', $user->id)->with('badge')->orderBy('id')->get()->unique('badge_id') as $award) {
            $events->push(['key' => 'badge:'.$award->badge_id, 'kind' => 'badge', 'day' => $award->day->toDateString(), 'title' => $award->badge->tr('title')]);
        }
        foreach ($user->goals()->where('status', Goal::STATUS_COMPLETED)->get() as $goal) {
            $events->push(['key' => 'goal:'.$goal->id, 'kind' => 'goal', 'day' => $local($goal->updated_at), 'title' => $goal->title]);
        }
        foreach (DB::table('skill_progress')->join('skill_levels', 'skill_levels.id', '=', 'skill_progress.skill_level_id')->where('user_id', $user->id)->get(['skill_levels.id', 'skill_levels.title', 'passed_at']) as $level) {
            $events->push(['key' => 'skill:'.$level->id, 'kind' => 'skill', 'day' => $local($level->passed_at), 'title' => $level->title]);
        }
        foreach ($user->portfolioProjects()->get() as $project) {
            $events->push(['key' => 'project:'.$project->id, 'kind' => 'project', 'day' => $project->completed_on?->toDateString() ?? $local($project->created_at), 'title' => $project->title]);
        }
        foreach (StorePurchase::where('user_id', $user->id)->join('store_items', 'store_items.id', '=', 'store_purchases.store_item_id')->get(['store_items.id', 'store_items.name', 'store_purchases.created_at']) as $purchase) {
            $events->push(['key' => 'purchase:'.$purchase->id, 'kind' => 'purchase', 'day' => $local($purchase->created_at), 'title' => $purchase->name]);
        }

        $entries = $user->timelineEntries()->get();
        foreach ($entries->whereNull('event_key') as $entry) {
            $events->push(['key' => 'custom:'.$entry->id, 'kind' => 'custom', 'day' => $entry->day->toDateString(), 'title' => $entry->title, 'entryId' => $entry->id, 'note' => $entry->note]);
        }
        $notes = $entries->whereNotNull('event_key')->pluck('note', 'event_key');

        // Stages: what each month amounted to.
        $stages = [];
        foreach ($user->tasks()->where('status', Task::STATUS_COMPLETED)->whereNotNull('completed_at')->pluck('completed_at') as $instant) {
            $month = substr($local($instant), 0, 7);
            $stages[$month]['tasks'] = ($stages[$month]['tasks'] ?? 0) + 1;
        }
        foreach (BadgeAward::where('user_id', $user->id)->get(['day', 'points']) as $award) {
            $month = $award->day->format('Y-m');
            $stages[$month]['points'] = ($stages[$month]['points'] ?? 0) + $award->points;
        }
        krsort($stages);

        return response()->json([
            'events' => $events->map(fn (array $event): array => ['note' => $notes[$event['key']] ?? '', ...$event])->sortByDesc('day')->values(),
            'stages' => collect($stages)->map(fn (array $stage, string $month): array => ['month' => $month, 'tasks' => $stage['tasks'] ?? 0, 'points' => $stage['points'] ?? 0])->values(),
        ]);
    }

    /** Attach (or, with an empty note, remove) a personal note on an automatic milestone. */
    public function note(Request $request, string $key): Response
    {
        $data = $request->validate(['note' => ['present', 'nullable', 'string', 'max:1000']]);
        $note = trim((string) $data['note']);
        $entry = $request->user()->timelineEntries()->firstOrNew(['event_key' => $key]);
        if ($note === '') {
            $entry->exists && $entry->delete();
        } else {
            $entry->fill(['note' => $note])->save();
        }

        return response()->noContent();
    }

    public function addMilestone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'day' => ['required', 'date_format:Y-m-d'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $entry = $request->user()->timelineEntries()->create(['title' => $data['title'], 'day' => $data['day'], 'note' => $data['note'] ?? '']);

        return response()->json(['id' => $entry->id], 201);
    }

    public function updateMilestone(Request $request, TimelineEntry $entry): Response
    {
        abort_unless($entry->user_id === $request->user()->id && $entry->event_key === null, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'day' => ['sometimes', 'date_format:Y-m-d'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $entry->update([...$data, ...(array_key_exists('note', $data) ? ['note' => $data['note'] ?? ''] : [])]);

        return response()->noContent();
    }

    public function deleteMilestone(Request $request, TimelineEntry $entry): Response
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
        $entry->delete();

        return response()->noContent();
    }

    // ── Goal roadmap ─────────────────────────────────────────────────────

    public function roadmap(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        return response()->json(['steps' => $goal->steps->map(fn (GoalStep $step): array => self::step($step))]);
    }

    public function addStep(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'requirements' => ['sometimes', 'nullable', 'string', 'max:500'],
            'parentId' => ['sometimes', 'nullable', 'integer'],
        ]);
        // A step hangs under a stage of the same goal; stages do not nest further.
        $parent = isset($data['parentId']) ? $goal->steps()->whereNull('parent_id')->findOrFail($data['parentId']) : null;
        $step = $goal->steps()->create([
            'title' => $data['title'],
            'requirements' => $data['requirements'] ?? '',
            'parent_id' => $parent?->id,
            'position' => (int) $goal->steps()->max('position') + 1,
        ]);

        return response()->json(['step' => self::step($step)], 201);
    }

    public function updateStep(Request $request, GoalStep $step): JsonResponse
    {
        abort_unless($step->goal->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'requirements' => ['sometimes', 'nullable', 'string', 'max:500'],
            'isDone' => ['sometimes', 'boolean'],
            'move' => ['sometimes', 'in:up,down'],
        ]);
        DB::transaction(function () use ($step, $data): void {
            $step->fill(array_filter(['title' => $data['title'] ?? null, 'is_done' => $data['isDone'] ?? null], fn ($value): bool => $value !== null));
            if (array_key_exists('requirements', $data)) {
                $step->requirements = $data['requirements'] ?? '';
            }
            if (isset($data['move'])) {
                // Swap places with the neighbour among its siblings.
                $siblings = GoalStep::where('goal_id', $step->goal_id)->where('parent_id', $step->parent_id)->orderBy('position')->orderBy('id')->get();
                $index = $siblings->search(fn (GoalStep $other): bool => $other->id === $step->id);
                $other = $siblings->get($index + ($data['move'] === 'up' ? -1 : 1));
                if ($other) {
                    [$step->position, $other->position] = [$other->position, $step->position];
                    $other->save();
                }
            }
            $step->save();
            // A stage with steps is done exactly when all of them are.
            if ($step->parent_id && array_key_exists('isDone', $data)) {
                GoalStep::whereKey($step->parent_id)->update(['is_done' => ! GoalStep::where('parent_id', $step->parent_id)->where('is_done', false)->exists()]);
            }
        });

        return response()->json(['steps' => $step->goal->steps()->get()->map(fn (GoalStep $item): array => self::step($item))]);
    }

    public function deleteStep(Request $request, GoalStep $step): Response
    {
        abort_unless($step->goal->user_id === $request->user()->id, 404);
        $step->delete();

        return response()->noContent();
    }

    private static function step(GoalStep $step): array
    {
        return ['id' => $step->id, 'parentId' => $step->parent_id, 'title' => $step->title, 'requirements' => $step->requirements, 'isDone' => $step->is_done, 'position' => $step->position];
    }

    // ── Backup ───────────────────────────────────────────────────────────

    /** Everything the member has entered, as one JSON file they can keep. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $data = [
            'exportedAt' => now()->toISOString(),
            'app' => 'SN Tasks',
            'profile' => $user->only(['display_name', 'surname', 'nickname', 'email', 'bio', 'timezone', 'language', 'specialties']),
            'categories' => $user->categories()->get(['id', 'name', 'color']),
            'tasks' => $user->tasks()->get()->makeHidden('user_id'),
            'goals' => $user->goals()->with('steps')->get()->makeHidden('user_id'),
            'habits' => $user->habits()->get()->makeHidden('user_id'),
            'habitChecks' => DB::table('habit_checks')->whereIn('habit_id', $user->habits()->select('id'))->get(['habit_id', 'day']),
            'moods' => $user->moodEntries()->get(['day', 'mood', 'motivation']),
            'dayEntries' => $user->dayEntries()->get()->map(fn ($entry): array => $entry->toApi()),
            'habitNotes' => $user->habitNotes()->get()->makeHidden(['id', 'user_id']),
            'reviews' => $user->dailyReviews()->get()->makeHidden(['id', 'user_id']),
            'focusSessions' => $user->focusSessions()->get(['day', 'minutes']),
            'timeline' => $user->timelineEntries()->get()->makeHidden(['id', 'user_id']),
            'visionBoards' => $user->visionBoards()->with('items')->get()->makeHidden('user_id'),
            'portfolio' => $user->portfolioProjects()->get()->map(fn (PortfolioProject $project): array => $project->toApi()),
            'messages' => $user->messages()->get()->makeHidden('user_id'),
        ];

        return response()->streamDownload(fn () => print (json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)), 'sn-tasks-backup-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }

    /** When the server last backed the whole database up (written by deploy/backup.sh). */
    public function backupStatus(): JsonResponse
    {
        $file = storage_path('app/backup-status.json');
        $status = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return response()->json(['lastBackupAt' => $status['lastBackupAt'] ?? null, 'ok' => $status['ok'] ?? null, 'serverTime' => now()->toISOString()]);
    }
}
