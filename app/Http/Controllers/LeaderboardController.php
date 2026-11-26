<?php

namespace App\Http\Controllers;

use App\Models\StoreItem;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The honour board: who finished today's tasks, who earned the most points,
 * and who was the most committed this week and this month.
 */
class LeaderboardController extends Controller
{
    private const SIZE = 10;

    public function show(Request $request): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $today = CarbonImmutable::parse($request->query('date', now($request->user()->timezone)->toDateString()));
        [, $weekStart, $weekEnd] = AchievementService::period('weekly', $today);

        // Daily tasks of one day or a range, per member: how many, and how many completed.

        // Members who chose to stay off the board.
        $hidden = User::whereNotNull('privacy')->get(['id', 'privacy'])
            ->reject(fn (User $user): bool => $user->privacyValues()['showOnLeaderboard'])->pluck('id')->all();
        $daily = fn (string $from, string $to) => Task::query()->where('period_type', 'daily')->where('status', '!=', 'cancelled')
            ->whereNotIn('user_id', $hidden)->whereDate('period_start', '>=', $from)->whereDate('period_start', '<=', $to);

        $todayRows = $daily($today->toDateString(), $today->toDateString())
            ->selectRaw('user_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [Task::STATUS_COMPLETED])
            ->groupBy('user_id')->havingRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) > 0', [Task::STATUS_COMPLETED])
            ->orderByDesc('done')->limit(self::SIZE)->get()
            ->map(fn ($row): array => ['userId' => (int) $row->user_id, 'value' => (int) $row->done, 'allDone' => (int) $row->done === (int) $row->total]);

        // A member's fully completed days this week; ties go to whoever completed more tasks.
        $weekRows = DB::query()->fromSub(
            $daily($weekStart->toDateString(), $weekEnd->toDateString())
                ->selectRaw('user_id, period_start, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [Task::STATUS_COMPLETED])
                ->groupBy('user_id', 'period_start'),
            'days',
        )->selectRaw('user_id, SUM(CASE WHEN done = total THEN 1 ELSE 0 END) as perfect, SUM(done) as completed')
            ->groupBy('user_id')->havingRaw('SUM(done) > 0')->orderByDesc('perfect')->orderByDesc('completed')->limit(self::SIZE)->get()
            ->map(fn ($row): array => ['userId' => (int) $row->user_id, 'value' => (int) $row->perfect, 'completed' => (int) $row->completed]);

        $points = fn (?string $from, ?string $to = null) => DB::table('badge_awards')->whereNotIn('user_id', $hidden)
            ->when($from, fn ($query) => $query->whereDate('day', '>=', $from))->when($to, fn ($query) => $query->whereDate('day', '<=', $to))
            ->selectRaw('user_id, SUM(points) as total')->groupBy('user_id')->havingRaw('SUM(points) > 0')
            ->orderByDesc('total')->limit(self::SIZE)->get()
            ->map(fn ($row): array => ['userId' => (int) $row->user_id, 'value' => (int) $row->total]);
        $pointRows = $points(null);
        $monthRows = $points($today->startOfMonth()->toDateString());
        $weekPointRows = $points($weekStart->toDateString(), $weekEnd->toDateString());

        $people = $this->people(collect([$todayRows, $weekRows, $weekPointRows, $pointRows, $monthRows])->flatten(1)->pluck('userId')->unique());
        $withPeople = fn (Collection $rows) => $rows->filter(fn (array $row): bool => $people->has($row['userId']))
            ->map(fn (array $row): array => [...$people[$row['userId']], ...$row])->values();

        return response()->json([
            'today' => $withPeople($todayRows),
            'week' => $withPeople($weekRows),
            'weekPoints' => $withPeople($weekPointRows),
            'points' => $withPeople($pointRows),
            'month' => $withPeople($monthRows),
        ]);
    }

    /**
     * The public face of each listed member: name, photo, and the title and frame they wear.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function people(Collection $ids): Collection
    {
        $users = User::whereIn('id', $ids)->get(['id', 'display_name', 'avatar_url', 'equipped', 'privacy']);
        $items = StoreItem::whereIn('id', $users->flatMap(fn (User $user) => array_filter($user->equipped ?? []))->unique())->get()->keyBy('id');
        $worn = fn (User $user, string $kind): ?string => $kind === 'title' ? $items->get($user->equipped[$kind] ?? null)?->tr('value') : $items->get($user->equipped[$kind] ?? null)?->value;

        return $users->mapWithKeys(function (User $user) use ($worn): array {
            $privacy = $user->privacyValues();

            return [$user->id => [
                ...$user->publicIdentity(),
                'title' => $privacy['showTitle'] ? $worn($user, 'title') : null,
                'frame' => $privacy['showFrame'] ? ($worn($user, 'frame') ?? 'none') : 'none',
            ]];
        });
    }
}
