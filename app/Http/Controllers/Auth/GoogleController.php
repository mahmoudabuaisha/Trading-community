<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Sign in with Google". The first visit creates the account from the Google
 * profile (or links an existing account with the same verified email); later
 * visits sign straight in. Off until GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET
 * are set in .env.
 */
class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('services.google.client_id') && (bool) config('services.google.client_secret');
    }

    /** Which sign-in providers the login page should offer. */
    public function providers(): JsonResponse
    {
        return response()->json(['google' => self::enabled()]);
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $error) {
            report($error);

            return redirect('/?google=failed');
        }
        $email = Str::lower((string) $google->getEmail());
        // Only addresses Google has verified can sign in or be linked.
        if ($email === '' || ! ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false)) {
            return redirect('/?google=failed');
        }

        $user = User::where('google_id', $google->getId())->first() ?? User::where('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'display_name' => Str::limit((string) ($google->getName() ?: Str::before($email, '@')), 120, ''),
                'email' => $email,
                // A long random password: the account signs in with Google (it can set one later by "forgot password").
                'password' => Str::random(48),
                'language' => $request->getPreferredLanguage(['ar', 'en', 'fr']) ?? 'ar',
            ]);
        }
        if (! $user->google_id) {
            $user->forceFill(['google_id' => $google->getId()])->save();
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect('/app/overview');
    }
}
