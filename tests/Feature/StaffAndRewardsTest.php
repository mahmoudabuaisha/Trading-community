<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\BroadcastSchedule;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $moderator = $this->moderator(['education', 'messages']);   // "messages" is ignored: see below
        $member = User::factory()->create();
        $this->actingAs($moderator);

        $this->getJson('/api/auth/me')->assertJsonPath('user.role', 'moderator')->assertJsonPath('user.permissions', ['education']);
        $this->getJson('/api/admin/overview')->assertOk()->assertJsonCount(0, 'audit');
        $this->getJson('/api/admin/lessons')->assertOk();
        $this->post('/api/admin/lessons', ['title' => 't', 'body' => 'b'], ['Accept' => 'application/json'])->assertCreated();
        // Private messages stay with full administrators, whatever a moderator's permissions say.
        $this->getJson('/api/admin/threads')->assertForbidden();
        $this->getJson("/api/admin/threads/{$member->id}")->assertForbidden();
        $this->postJson("/api/admin/threads/{$member->id}", ['body' => 'hi'])->assertForbidden();
        // Staff read subscriber-only lessons.
        $this->assertTrue($moderator->hasGrant('lesson', 1));

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
        $this->actingAs($usersModerator)->patchJson("/api/admin/users/{$member->id}", ['isPremium' => true])->assertOk()->assertJsonPath('user.isPremium', true);
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

    public function test_private_messages_reach_only_their_member_and_full_administrators(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$sara, $omar] = [User::factory()->create(), User::factory()->create()];
        $moderator = $this->moderator(['users', 'growth', 'audit']);

        $this->actingAs($sara)->postJson('/api/messages', ['body' => 'سؤال خاص'])->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/threads/{$sara->id}", ['body' => 'رد خاص'])->assertCreated();

        // Sara sees her own thread; Omar sees nothing of it, by any route.
        $this->actingAs($sara)->getJson('/api/messages')->assertJsonCount(2, 'messages');
        $this->actingAs($omar)->getJson('/api/messages')->assertJsonCount(0, 'messages');
        foreach ([$omar, $moderator] as $outsider) {
            $this->actingAs($outsider)->getJson('/api/admin/threads')->assertForbidden();
            $this->actingAs($outsider)->getJson("/api/admin/threads/{$sara->id}")->assertForbidden();
            $this->actingAs($outsider)->postJson("/api/admin/threads/{$sara->id}", ['body' => 'x'])->assertForbidden();
        }
        // A moderator who manages accounts sees the account, never its messages; and the area cannot be granted.
        $details = $this->actingAs($moderator)->getJson("/api/admin/users/{$sara->id}")->assertOk()->json();
        $this->assertStringNotContainsString('سؤال خاص', json_encode($details, JSON_UNESCAPED_UNICODE));
        $this->actingAs($admin)->patchJson("/api/admin/users/{$moderator->id}", ['permissions' => ['messages']])->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/threads')->assertOk()->assertJsonCount(1, 'threads');
    }

    public function test_account_details_gather_what_a_member_did(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['nickname' => 'أبو خالد']);
        Task::factory()->for($member)->create(['title' => 'منجزة', 'status' => 'completed', 'completed_at' => now()]);
        Task::factory()->for($member)->create(['title' => 'معلقة']);
        Task::factory()->for($admin)->create(['title' => 'مهمة غيره']);

        $this->actingAs($member)->getJson("/api/admin/users/{$member->id}")->assertForbidden();
        $this->actingAs($admin)->getJson("/api/admin/users/{$member->id}")->assertOk()
            ->assertJsonPath('user.nickname', 'أبو خالد')->assertJsonPath('user.email', $member->email)
            ->assertJsonPath('counts.tasks', 2)->assertJsonPath('counts.tasksCompleted', 1)
            ->assertJsonCount(2, 'tasks')->assertJsonCount(1, 'completedTasks')->assertJsonPath('completedTasks.0.title', 'منجزة')
            ->assertJsonPath('points.balance', 0)->assertJsonCount(0, 'purchases');
    }

    public function test_recurring_messages_arrive_at_their_time_in_the_schedule_timezone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        // Thursday 26 November 2026, 07:30 in Palestine (05:30 UTC).
        $at = fn (string $utc) => [CarbonImmutable::setTestNow($utc), Carbon::setTestNow($utc)];
        $at('2026-11-26 05:30:00');

        $this->actingAs($member)->postJson('/api/admin/broadcast-schedules', ['frequency' => 'daily', 'time' => '08:00', 'body' => 'x'])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/broadcast-schedules', ['frequency' => 'weekly', 'time' => '08:00', 'body' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('weekday');
        $this->postJson('/api/admin/broadcast-schedules', ['frequency' => 'daily', 'time' => '08:00', 'body' => 'صباح الخير'])->assertCreated()->assertJsonPath('timezone', 'Asia/Hebron');
        $this->postJson('/api/admin/broadcast-schedules', ['frequency' => 'weekly', 'weekday' => 4, 'time' => '20:00', 'body' => 'مراجعة الأسبوع'])->assertCreated()->assertJsonCount(2, 'schedules');
        $this->postJson('/api/admin/broadcast-schedules', ['frequency' => 'weekly', 'weekday' => 5, 'time' => '06:00', 'body' => 'رسالة الجمعة'])->assertCreated();

        $bodies = fn () => array_column($this->actingAs($member)->getJson('/api/messages/unread')->json('broadcasts'), 'body');
        $this->assertSame([], $bodies());                       // 07:30 — nothing is due yet
        $at('2026-11-26 06:05:00');
        $this->assertSame(['صباح الخير'], $bodies());          // 08:05 — the daily one
        $at('2026-11-26 18:30:00');
        $this->assertSame(['صباح الخير', 'مراجعة الأسبوع'], $bodies());   // 20:30 on a Thursday
        $first = $this->getJson('/api/messages/unread')->json('broadcasts.0.id');
        $at('2026-11-27 06:30:00');
        $this->assertSame(['رسالة الجمعة', 'صباح الخير'], $bodies());    // Friday 08:30: a new day, a new occurrence
        $this->assertNotSame($first, $this->getJson('/api/messages/unread')->json('broadcasts.1.id'));

        $id = BroadcastSchedule::where('frequency', 'daily')->value('id');
        $this->actingAs($admin)->patchJson("/api/admin/broadcast-schedules/{$id}", ['isActive' => false])->assertOk();
        $this->assertSame(['رسالة الجمعة'], $bodies());
        $this->actingAs($admin)->deleteJson("/api/admin/broadcast-schedules/{$id}")->assertNoContent();
    }
}
