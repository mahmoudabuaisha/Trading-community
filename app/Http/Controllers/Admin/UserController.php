<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\BadgeAward;
use App\Models\Goal;
use App\Models\StorePurchase;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\AdminService;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('area', 'users');

        $search = trim((string) $request->query('search', ''));

        $page = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q
                    ->where('display_name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->latest()
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => AdminUserResource::collection($page->items()),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Everything about one account, for the administrators' "Details" view. Private messages are not part of it. */
    public function show(User $user, PointsService $points, AchievementService $achievements): JsonResponse
    {
        Gate::authorize('area', 'users');
        $task = fn (Task $item): array => ['id' => $item->id, 'title' => $item->title, 'periodType' => $item->period_type, 'status' => $item->status, 'createdAt' => $item->created_at->toISOString(), 'completedAt' => $item->completed_at?->toISOString()];
        $awards = BadgeAward::where('user_id', $user->id)->with('badge')->get()->groupBy('badge_id');

        return response()->json([
            'user' => [
                ...(new AdminUserResource($user))->resolve(),
                'surname' => $user->surname,
                'nickname' => $user->nickname,
                'bio' => $user->bio,
                'specialties' => $user->specialties ?? [],
                'gender' => $user->gender,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'lastSeenAt' => $user->last_seen_at?->toISOString(),
                ...$points->appearance($user),
            ],
            'points' => ['balance' => $points->balance($user), 'earned' => $points->earned($user)],
            'counts' => [
                'tasks' => $user->tasks()->count(),
                'tasksCompleted' => $user->tasks()->where('status', Task::STATUS_COMPLETED)->count(),
                'goals' => $user->goals()->count(),
                'goalsCompleted' => $user->goals()->where('status', Goal::STATUS_COMPLETED)->count(),
                'habits' => $user->habits()->count(),
                'focusMinutes' => (int) $user->focusSessions()->sum('minutes'),
                'streak' => $achievements->currentStreak($user),
                'skillLevels' => DB::table('skill_progress')->where('user_id', $user->id)->count(),
                'projects' => $user->portfolioProjects()->count(),
                'visionBoards' => $user->visionBoards()->count(),
            ],
            // The latest of each; the counts above cover the rest.
            'tasks' => $user->tasks()->latest()->latest('id')->limit(50)->get()->map($task),
            'completedTasks' => $user->tasks()->where('status', Task::STATUS_COMPLETED)->latest('completed_at')->limit(50)->get()->map($task),
            'goals' => $user->goals()->latest()->limit(30)->get()->map(fn (Goal $goal): array => ['id' => $goal->id, 'title' => $goal->title, 'status' => $goal->status, 'currentValue' => $goal->current_value, 'targetValue' => $goal->target_value]),
            'achievements' => $user->achievements()->orderBy('earned_at')->get()->map(fn ($item): array => ['key' => $item->key, 'title' => null, 'points' => 0, 'times' => 1, 'earnedAt' => $item->earned_at->toISOString()]),
            'badges' => $awards->map(fn ($group): array => ['key' => 'badge:'.$group->first()->badge_id, 'title' => $group->first()->badge->title, 'points' => (int) $group->sum('points'), 'times' => $group->count(), 'earnedAt' => $group->max('created_at')->toISOString()])->values(),
            'purchases' => StorePurchase::where('user_id', $user->id)->join('store_items', 'store_items.id', '=', 'store_purchases.store_item_id')->latest('store_purchases.id')
                ->get(['store_items.name', 'store_items.kind', 'store_purchases.price', 'store_purchases.created_at'])
                ->map(fn ($row): array => ['name' => $row->name, 'kind' => $row->kind, 'price' => (int) $row->price, 'boughtAt' => $row->created_at->toISOString()]),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AdminService $admin): JsonResponse
    {
        $user = $admin->updateUser($request->user(), $user, $request->attributesForModel());

        return response()->json(['user' => new AdminUserResource($user)]);
    }

    public function revokeSessions(Request $request, User $user, AdminService $admin): Response
    {
        Gate::authorize('area', 'users');

        $admin->revokeSessions($request->user(), $user);

        return response()->noContent();
    }
}
