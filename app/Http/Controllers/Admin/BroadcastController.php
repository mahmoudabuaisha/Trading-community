<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Services\AdminService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Messages to every member: a schedule of one per day, and ones sent on the spot. */
class BroadcastController extends Controller
{
    /** The scheduled messages of the 7 days starting at `from`, and the latest instant ones. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'date_format:Y-m-d']]);
        $from = CarbonImmutable::parse($data['from']);
        $rows = Broadcast::where('kind', Broadcast::KIND_SCHEDULED)
            ->whereDate('day', '>=', $from->toDateString())->whereDate('day', '<=', $from->addDays(6)->toDateString())->orderBy('day')->get();
        $scheduled = $rows->mapWithKeys(fn (Broadcast $item): array => [$item->day->toDateString() => $item->body]);

        return response()->json([
            'scheduled' => (object) $scheduled->all(),
            // Day → id, so each day's message can be translated.
            'scheduledIds' => (object) $rows->mapWithKeys(fn (Broadcast $item): array => [$item->day->toDateString() => $item->id])->all(),
            'instant' => Broadcast::where('kind', Broadcast::KIND_INSTANT)->latest('id')->limit(10)->get()
                ->map(fn (Broadcast $item): array => ['id' => $item->id, 'body' => $item->body, 'day' => $item->day->toDateString(), 'createdAt' => $item->created_at->toISOString()]),
        ]);
    }

    /** Set, replace or (with an empty body) clear the message of one day. */
    public function schedule(Request $request, string $day, AdminService $admin): Response
    {
        $data = $request->validate(['body' => ['present', 'nullable', 'string', 'max:500']]);
        $admin->scheduleBroadcast($request->user(), $day, trim((string) $data['body']));

        return response()->noContent();
    }

    /** Send a message now. `day` is the sender's local day, so members see it today. */
    public function send(Request $request, AdminService $admin): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:500'], 'day' => ['required', 'date_format:Y-m-d']]);
        $broadcast = $admin->sendBroadcast($request->user(), $data['day'], trim($data['body']));

        return response()->json(['id' => $broadcast->id], 201);
    }

    public function destroy(Request $request, Broadcast $broadcast, AdminService $admin): Response
    {
        $admin->deleteBroadcast($request->user(), $broadcast);

        return response()->noContent();
    }
}
