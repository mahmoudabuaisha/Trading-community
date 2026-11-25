<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\RewardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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

    /** Equip a frame and a title the member has enough points for. */
    public function rewards(Request $request, RewardService $rewards): JsonResponse
    {
        $data = $request->validate([
            'frame' => ['required', 'string', Rule::in(array_keys(config('rewards.frames')))],
            'titleKey' => ['present', 'nullable', 'string', Rule::in(array_keys(config('rewards.titles')))],
        ]);
        $user = $request->user();
        if (! $rewards->canEquipFrame($user, $data['frame'])) {
            throw ValidationException::withMessages(['frame' => __('validation.custom.rewards.locked')]);
        }
        if (! $rewards->canEquipTitle($user, $data['titleKey'])) {
            throw ValidationException::withMessages(['titleKey' => __('validation.custom.rewards.locked')]);
        }
        $user->forceFill(['frame' => $data['frame'], 'title_key' => $data['titleKey']])->save();

        return response()->json(['user' => new UserResource($user)]);
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
