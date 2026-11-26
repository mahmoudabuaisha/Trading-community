<?php

namespace App\Http\Controllers;

use App\Models\DailyTip;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Your tip of the day": free for every member, one draw per local day.
 * Nothing here touches points or subscriptions.
 */
class DailyTipController extends Controller
{
    /** Today's line if it was drawn, and whether a draw is possible. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request));
    }

    /**
     * Draw today's line. `reel` holds other lines for the spinning animation,
     * ending on the drawn one. A second draw on the same day returns the first.
     */
    public function draw(Request $request): JsonResponse
    {
        $user = $request->user();
        $day = $this->day($request);
        $existing = $user->dailyTipDraws()->whereDate('day', $day)->first();
        if (! $existing) {
            $tip = DailyTip::where('is_active', true)->inRandomOrder()->first();
            abort_unless($tip, 422, __('validation.custom.tips.empty'));
            try {
                $existing = $user->dailyTipDraws()->create(['daily_tip_id' => $tip->id, 'day' => $day, 'body' => $tip->body]);
            } catch (UniqueConstraintViolationException) {
                // Two taps at once: the first draw stands.
                $existing = $user->dailyTipDraws()->whereDate('day', $day)->firstOrFail();
            }
        }
        $reel = DailyTip::where('is_active', true)->where('id', '!=', $existing->daily_tip_id)->inRandomOrder()->limit(14)->get()->map(fn (DailyTip $tip): string => $tip->tr('body'))->all();

        return response()->json([...$this->state($request), 'reel' => [...$reel, $this->text($existing)]]);
    }

    private function state(Request $request): array
    {
        $draw = $request->user()->dailyTipDraws()->with('tip')->whereDate('day', $this->day($request))->first();

        return [
            'tip' => $draw ? $this->text($draw) : null,
            'available' => ! $draw && DailyTip::where('is_active', true)->exists(),
            'day' => $this->day($request),
        ];
    }

    /** The drawn line in the member's language while it still exists, or as it was when drawn. */
    private function text($draw): string
    {
        return $draw->tip ? $draw->tip->tr('body') : $draw->body;
    }

    /** "Today" is the member's local day, supplied by the client like elsewhere in the app. */
    private function day(Request $request): string
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        return $request->input('date', now($request->user()->timezone)->toDateString());
    }
}
