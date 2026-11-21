<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Second, independent admin check behind the `role:admin` route middleware.
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        // Password-reset emails link to the SPA screen.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => url(route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false)));
    }
}
