<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
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

    private function endOtherSessions(Request $request): void
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
