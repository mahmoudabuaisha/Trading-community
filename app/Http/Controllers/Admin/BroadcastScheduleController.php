<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BroadcastSchedule;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Messages that repeat every day or every week at a set time. */
class BroadcastScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        return self::all();
    }

    public function store(Request $request, AdminService $admin): JsonResponse
    {
        $schedule = new BroadcastSchedule($this->data($request, 'required'));
        $schedule->created_by = $request->user()->id;
        $schedule->save();
        $admin->record($request->user(), 'broadcast_schedule_created', mb_strimwidth($schedule->body, 0, 60, '…'));

        return self::all(201);
    }

    public function update(Request $request, BroadcastSchedule $schedule, AdminService $admin): JsonResponse
    {
        $schedule->update($this->data($request, 'sometimes'));
        $admin->record($request->user(), 'broadcast_schedule_updated', mb_strimwidth($schedule->body, 0, 60, '…'));

        return self::all();
    }

    public function destroy(Request $request, BroadcastSchedule $schedule, AdminService $admin): Response
    {
        $schedule->delete();
        $admin->record($request->user(), 'broadcast_schedule_deleted', mb_strimwidth($schedule->body, 0, 60, '…'));

        return response()->noContent();
    }

    private function data(Request $request, string $required): array
    {
        $data = $request->validate([
            'frequency' => [$required, Rule::in(BroadcastSchedule::FREQUENCIES)],
            // Monday = 1 … Sunday = 7. Needed for a weekly message, meaningless for a daily one.
            'weekday' => [Rule::requiredIf($request->input('frequency') === 'weekly'), 'nullable', 'integer', 'between:1,7'],
            'time' => [$required, 'date_format:H:i'],
            'body' => [$required, 'string', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
        $attributes = array_filter(['frequency' => $data['frequency'] ?? null, 'time' => $data['time'] ?? null, 'body' => isset($data['body']) ? trim($data['body']) : null, 'is_active' => $data['isActive'] ?? null], fn ($value): bool => $value !== null);
        if (isset($data['frequency'])) {
            $attributes['weekday'] = $data['frequency'] === 'weekly' ? (int) $data['weekday'] : null;
        }

        return $attributes;
    }

    private static function all(int $status = 200): JsonResponse
    {
        return response()->json([
            'timezone' => config('app.schedule_timezone'),
            'schedules' => BroadcastSchedule::orderBy('frequency')->orderBy('weekday')->orderBy('time')->get()->map(fn (BroadcastSchedule $schedule): array => [
                'id' => $schedule->id, 'frequency' => $schedule->frequency, 'weekday' => $schedule->weekday, 'time' => $schedule->time, 'body' => $schedule->body, 'isActive' => $schedule->is_active,
            ]),
        ], $status);
    }
}
