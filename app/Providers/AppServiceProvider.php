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
        // One area of the admin center: administrators always, moderators when granted.
        Gate::define('area', fn (User $user, string $area): bool => $user->hasPermission($area));
        Gate::define('staff', fn (User $user): bool => $user->isStaff());

        // Password-reset emails link to the SPA screen.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => url(route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false)));
    }
}
