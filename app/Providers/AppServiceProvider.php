<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\User;
use App\Services\Assistant\AssistantClient;
use App\Services\Assistant\ClaudeAssistantClient;
use App\Services\Translation\ClaudeTextTranslator;
use App\Services\Translation\TextTranslator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The AI assistant talks to Claude; tests swap in a fake.
        $this->app->bind(AssistantClient::class, fn () => new ClaudeAssistantClient(
            new Client(apiKey: (string) config('assistant.api_key')),
            (string) config('assistant.model'),
        ));
        $this->app->bind(TextTranslator::class, fn () => new ClaudeTextTranslator(
            new Client(apiKey: (string) config('assistant.api_key')),
            (string) config('assistant.translation_model'),
        ));
    }

    public function boot(): void
    {
        // 300 API calls a minute per member is far above normal use; bots hitting the API stop there.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));
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
