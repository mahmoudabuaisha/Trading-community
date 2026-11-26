<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyTip;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The lines members draw as their tip of the day. */
class DailyTipController extends Controller
{
    public function index(): JsonResponse
    {
        return self::all();
    }

    /** Add one line, or many at once: `bodies` takes a list (one line per entry). */
    public function store(Request $request, AdminService $admin): JsonResponse
    {
        $data = $request->validate([
            'bodies' => ['required', 'array', 'min:1', 'max:300'],
            'bodies.*' => ['nullable', 'string', 'max:300'],
        ]);
        $bodies = array_values(array_unique(array_filter(array_map(fn (?string $body): string => trim((string) $body), $data['bodies']))));
        foreach ($bodies as $body) {
            DailyTip::create(['body' => $body, 'created_by' => $request->user()->id]);
        }
        $admin->record($request->user(), 'daily_tips_added', (string) count($bodies));

        return self::all(201);
    }

    public function update(Request $request, DailyTip $tip, AdminService $admin): JsonResponse
    {
        $data = $request->validate(['body' => ['sometimes', 'string', 'max:300'], 'isActive' => ['sometimes', 'boolean']]);
        $tip->fill(array_filter(['body' => isset($data['body']) ? trim($data['body']) : null, 'is_active' => $data['isActive'] ?? null], fn ($value): bool => $value !== null))->save();
        $admin->record($request->user(), 'daily_tip_updated', mb_strimwidth($tip->body, 0, 60, '…'));

        return self::all();
    }

    public function destroy(Request $request, DailyTip $tip, AdminService $admin): Response
    {
        $tip->delete();
        $admin->record($request->user(), 'daily_tip_deleted', mb_strimwidth($tip->body, 0, 60, '…'));

        return response()->noContent();
    }

    private static function all(int $status = 200): JsonResponse
    {
        return response()->json(['tips' => DailyTip::latest('id')->get()->map(fn (DailyTip $tip): array => ['id' => $tip->id, 'body' => $tip->body, 'isActive' => $tip->is_active])], $status);
    }
}
