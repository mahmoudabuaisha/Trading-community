<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_plain_user_with_default_categories(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'displayName' => 'Nora',
            'email' => 'nora@example.com',
            'password' => 'secret-pass-1',
            'role' => 'admin', // must be ignored
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'nora@example.com')
            ->assertJsonPath('user.displayName', 'Nora')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonMissingPath('user.password');

        $user = User::where('email', 'nora@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertSame(4, $user->categories()->count());
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_register_validates_input_with_field_errors(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', ['displayName' => '', 'email' => 'taken@example.com', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['displayName', 'email', 'password']]);
    }

    public function test_validation_messages_follow_accept_language(): void
    {
        $arabic = $this->postJson('/api/auth/login', [], ['Accept-Language' => 'ar'])->assertStatus(422);
        $english = $this->postJson('/api/auth/login', [], ['Accept-Language' => 'en'])->assertStatus(422);

        $this->assertStringContainsString('مطلوب', $arabic->json('errors.email.0'));
        $this->assertStringContainsString('required', $english->json('errors.email.0'));
    }

    public function test_login_me_and_logout_flow(): void
    {
        $user = User::factory()->create(['email' => 'sobhi@example.com', 'password' => 'correct-horse']);

        $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->postJson('/api/auth/login', ['email' => 'sobhi@example.com', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest('web');

        $this->postJson('/api/auth/login', ['email' => 'sobhi@example.com', 'password' => 'correct-horse', 'remember' => true])
            ->assertOk()->assertJsonPath('user.id', $user->id);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email', 'sobhi@example.com');

        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_same_origin_requests_are_stateful_on_any_host_or_port(): void
    {
        // Regression: cookie auth must not depend on APP_URL matching the real host.
        $user = User::factory()->create(['password' => 'correct-horse']);
        $origin = ['Origin' => 'http://173.212.249.190:8080'];

        $this->postJson('http://173.212.249.190:8080/api/auth/login', ['email' => $user->email, 'password' => 'correct-horse'], $origin)->assertOk();
    }

    public function test_protected_endpoints_answer_401_json_when_signed_out(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->postJson('/api/tasks', [])->assertUnauthorized();
    }

    public function test_forgot_password_never_reveals_whether_the_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_a_valid_token_only(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token, $user) {
            $token = $notification->token;
            $this->assertStringContainsString('/reset-password/'.$token, $notification->toMail($user)->actionUrl);

            return true;
        });

        $this->postJson('/api/auth/reset-password', ['token' => 'bogus', 'email' => $user->email, 'password' => 'brand-new-pass'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'brand-new-pass'])
            ->assertOk()->assertJsonStructure(['message']);

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }
}
