<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffAndRewardsTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(array $permissions): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'moderator', 'permissions' => $permissions])->save();

        return $user;
    }

    public function test_a_moderator_reaches_only_the_areas_they_were_granted(): void
    {
        $moderator = $this->moderator(['education', 'messages']);
        $member = User::factory()->create();
        $this->actingAs($moderator);

        $this->getJson('/api/auth/me')->assertJsonPath('user.role', 'moderator')->assertJsonPath('user.permissions', ['education', 'messages']);
        $this->getJson('/api/admin/overview')->assertOk()->assertJsonCount(0, 'audit');
        $this->getJson('/api/admin/lessons')->assertOk();
        $this->post('/api/admin/lessons', ['title' => 't', 'body' => 'b'], ['Accept' => 'application/json'])->assertCreated();
        $this->getJson('/api/admin/threads')->assertOk();
        $this->postJson("/api/admin/threads/{$member->id}", ['body' => 'hi'])->assertCreated();
        // Staff read subscriber-only lessons.
        $this->assertTrue($moderator->canReadPremium());

        foreach ([['GET', '/api/admin/users'], ['GET', '/api/admin/motivations'], ['GET', '/api/admin/badges'], ['GET', '/api/admin/settings'],
            ['PATCH', "/api/admin/users/{$member->id}"], ['POST', "/api/admin/users/{$member->id}/revoke-sessions"]] as [$method, $uri]) {
            $this->json($method, $uri, ['displayName' => 'x', 'isPremium' => true])->assertForbidden();
        }
    }

    public function test_only_a_full_admin_decides_who_is_staff_and_with_which_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $usersModerator = $this->moderator(['users']);
        $member = User::factory()->create();

        // A moderator with the users area edits accounts and grants premium…
        $this->actingAs($usersModerator)->patchJson("/api/admin/users/{$member->id}", ['displayName' => 'جديد', 'isPremium' => true])->assertOk()->assertJsonPath('user.isPremium', true);
        // …but cannot promote anyone, including themselves, or hand out permissions.
        $this->actingAs($usersModerator)->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator'])->assertForbidden();
        $this->actingAs($usersModerator)->patchJson("/api/admin/users/{$usersModerator->id}", ['role' => 'admin'])->assertForbidden();
        $this->actingAs($usersModerator)->patchJson("/api/admin/users/{$usersModerator->id}", ['permissions' => ['users', 'badges']])->assertForbidden();
        $this->assertSame('user', $member->refresh()->role);

        $this->actingAs($admin)->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator', 'permissions' => ['growth', 'badges', 'nonsense']])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions.2');
        $this->actingAs($admin)->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator', 'permissions' => ['badges', 'growth']])
            ->assertOk()->assertJsonPath('user.role', 'moderator')->assertJsonPath('user.permissions', ['growth', 'badges']);
        $this->actingAs($member->refresh())->getJson('/api/admin/badges')->assertOk();
        $this->actingAs($member)->getJson('/api/admin/lessons')->assertForbidden();

        // Back to a plain member: the admin center closes entirely.
        $this->actingAs($admin)->patchJson("/api/admin/users/{$member->id}", ['role' => 'user'])->assertOk();
        $this->actingAs($member->refresh())->getJson('/api/admin/overview')->assertForbidden();
        $this->assertContains('permissions_changed', AdminAudit::pluck('action')->all());
    }

    public function test_preferences_sessions_and_absence(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $this->actingAs($user);

        $this->getJson('/api/auth/me')->assertJsonPath('user.preferences.notifyMorning', true)->assertJsonPath('user.preferences.sleepTime', null);
        $prefs = ['notifyReminders' => true, 'notifyMorning' => false, 'notifyInactivity' => true, 'notifySleep' => true, 'sleepTime' => '23:00', 'wakeTime' => '06:30'];
        $this->putJson('/api/profile/preferences', $prefs)->assertOk()->assertJsonPath('user.preferences.notifyMorning', false)->assertJsonPath('user.preferences.wakeTime', '06:30');
        $this->putJson('/api/profile/preferences', [...$prefs, 'sleepTime' => '25:99'])->assertUnprocessable()->assertJsonValidationErrors('sleepTime');
        $this->putJson('/api/profile', ['displayName' => 'x', 'surname' => '', 'bio' => '', 'timezone' => 'UTC', 'language' => 'fr', 'weeklyGoal' => 5, 'dailyFocusMinutes' => 30, 'avatarUrl' => null])
            ->assertOk()->assertJsonPath('user.language', 'fr');

        DB::table('sessions')->insert([
            ['id' => 'mine', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36', 'payload' => '', 'last_activity' => time()],
            ['id' => 'other', 'user_id' => User::factory()->create()->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
        ]);
        $this->getJson('/api/profile/sessions')->assertOk()->assertJsonCount(1, 'sessions')->assertJsonPath('sessions.0.device', 'Chrome · Windows')->assertJsonPath('sessions.0.ip', '10.0.0.1');

        // First visit: nothing to report. Three days later: two whole days away, reported once.
        $this->getJson('/api/dashboard?date=2026-11-10')->assertJsonPath('absenceDays', 0);
        $user->forceFill(['last_seen_at' => '2026-11-10 09:00:00'])->save();
        $this->getJson('/api/dashboard?date=2026-11-11')->assertJsonPath('absenceDays', 0);
        $user->forceFill(['last_seen_at' => '2026-11-11 09:00:00'])->save();
        $this->travelTo('2026-11-14 10:00:00');
        $this->getJson('/api/dashboard?date=2026-11-14')->assertJsonPath('absenceDays', 2);
        $this->getJson('/api/dashboard?date=2026-11-14')->assertJsonPath('absenceDays', 0);
    }
}
