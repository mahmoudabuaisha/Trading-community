<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PointsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(ProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->attributesForModel());

        return response()->json(['user' => new UserResource($user)]);
    }

    /** Change the password after proving the current one; every other device is signed out. */
    public function password(Request $request): Response
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::min(8), 'different:currentPassword'],
        ]);

        $request->user()->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
        $this->endOtherSessions($request);

        return response()->noContent();
    }

    /** Sign this account out everywhere except the device making the request. */
    public function signOutOthers(Request $request): Response
    {
        $request->user()->forceFill(['remember_token' => null])->save();
        $this->endOtherSessions($request);

        return response()->noContent();
    }

    /** Devices currently signed in to this account, most recent first. */
    public function sessions(Request $request): JsonResponse
    {
        $current = $request->session()->getId();
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->limit(20)->get()
            ->map(fn (object $row): array => [
                'device' => self::describeAgent((string) $row->user_agent),
                'ip' => $row->ip_address,
                'lastActiveAt' => CarbonImmutable::createFromTimestamp($row->last_activity)->toISOString(),
                'current' => $row->id === $current,
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    /** Notification choices and sleep/wake times. */
    public function preferences(Request $request): JsonResponse
    {
        $time = ['present', 'nullable', 'date_format:H:i'];
        $data = $request->validate([
            'notifyReminders' => ['required', 'boolean'],
            'notifyMorning' => ['required', 'boolean'],
            'notifyInactivity' => ['required', 'boolean'],
            'notifySleep' => ['required', 'boolean'],
            'sleepTime' => $time,
            'wakeTime' => $time,
        ]);
        $user = $request->user();
        $user->forceFill(['preferences' => $data])->save();

        return response()->json(['user' => new UserResource($user)]);
    }

    /** What other members see of this account. */
    public function privacy(Request $request): JsonResponse
    {
        // Any subset may be sent; the rest keep their current value.
        $data = $request->validate(array_fill_keys(array_keys(User::DEFAULT_PRIVACY), ['sometimes', 'boolean']));
        $user = $request->user();
        $user->forceFill(['privacy' => array_map('boolval', [...$user->privacyValues(), ...$data])])->save();

        return response()->json(['user' => new UserResource($user)]);
    }

    /** Points earned per day over the last 30 days, for the profile chart. */
    public function points(Request $request, PointsService $points): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $user = $request->user();

        return response()->json([
            'earned' => $points->earned($user),
            'balance' => $points->balance($user),
            'history' => $points->history($user, $request->query('date', now($user->timezone)->toDateString())),
        ]);
    }

    /** "Chrome on Windows" from a user-agent string; good enough to recognise your own devices. */
    private static function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $system = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser.($system ? ' · '.$system : ''));
    }

    private function endOtherSessions(Request $request): void
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
