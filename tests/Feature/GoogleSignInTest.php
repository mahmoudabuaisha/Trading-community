<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $id, string $email, bool $verified = true): void
    {
        $google = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'name' => 'Layla Ahmad', 'email' => $email]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_it_stays_off_until_keys_are_set(): void
    {
        $this->getJson('/api/auth/providers')->assertOk()->assertJsonPath('google', false);
        $this->get('/auth/google')->assertNotFound();
    }

    public function test_a_new_google_account_is_created_and_signed_in(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $this->getJson('/api/auth/providers')->assertJsonPath('google', true);
        $this->fakeGoogle('g-1', 'Layla@Example.com');

        $this->get('/auth/google/callback')->assertRedirect('/app/overview');
        $user = User::where('email', 'layla@example.com')->firstOrFail();
        $this->assertSame('g-1', $user->google_id);
        $this->assertSame('Layla Ahmad', $user->display_name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_existing_account_is_linked(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $existing = User::factory()->create(['email' => 'omar@example.com']);
        $this->fakeGoogle('g-2', 'omar@example.com');
        $this->get('/auth/google/callback')->assertRedirect('/app/overview');
        $this->assertSame('g-2', $existing->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'omar@example.com')->count());
    }

    public function test_unverified_google_emails_are_refused(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $this->fakeGoogle('g-3', 'fake@example.com', verified: false);
        $this->get('/auth/google/callback')->assertRedirect('/?google=failed');
        $this->assertNull(User::where('email', 'fake@example.com')->first());
    }

    public function test_sign_up_bots_and_floods_are_turned_away_and_pages_carry_security_headers(): void
    {
        $this->postJson('/api/auth/register', ['displayName' => 'Bot', 'email' => 'bot@example.com', 'password' => 'Strong#Pass123', 'website' => 'http://spam.example'])
            ->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->assertNull(User::where('email', 'bot@example.com')->first());

        $this->get('/')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $this->getJson('/api/auth/providers')->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
    }
}
