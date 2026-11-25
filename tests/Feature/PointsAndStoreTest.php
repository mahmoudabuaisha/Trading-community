<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\Broadcast;
use App\Models\StoreItem;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PointsAndStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday; the week runs Monday 23 to Sunday 29 November.
        CarbonImmutable::setTestNow('2026-11-25 10:00:00');
        Carbon::setTestNow('2026-11-25 10:00:00');
    }

    private function dailyTask(User $user, string $day, string $status = 'pending'): Task
    {
        return Task::factory()->for($user)->create(['period_type' => 'daily', 'period_start' => $day, 'period_end' => $day, 'status' => $status, 'completed_at' => $status === 'completed' ? $day.' 09:00:00' : null]);
    }

    private function award(User $user, int $points, string $day = '2026-11-25'): void
    {
        $badge = Badge::create(['title' => 'منحة', 'points' => $points, 'period' => 'once', 'conditions' => [['metric' => 'tasks_completed', 'threshold' => 1000000]]]);
        BadgeAward::create(['user_id' => $user->id, 'badge_id' => $badge->id, 'period_key' => 'once', 'points' => $points, 'day' => $day]);
    }

    public function test_a_daily_badge_awards_its_points_once_per_day_when_every_condition_holds(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $badge = Badge::create(['title' => 'يوم مكتمل', 'points' => 10, 'period' => 'daily', 'conditions' => [
            ['metric' => 'perfect_days', 'threshold' => 1], ['metric' => 'tasks_completed', 'threshold' => 2],
        ]]);
        [$first, $second] = [$this->dailyTask($user, '2026-11-25'), $this->dailyTask($user, '2026-11-25')];
        $complete = fn (Task $task) => $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertOk();
        $keys = fn ($response) => collect($response->json('newAchievements'))->pluck('key')->all();

        // One of two done: neither condition holds yet.
        $this->assertNotContains($badge->achievementKey(), $keys($complete($first)));
        $this->getJson('/api/auth/me')->assertJsonPath('user.points', 0);
        $this->assertContains($badge->achievementKey(), $keys($complete($second)));
        $this->getJson('/api/auth/me')->assertJsonPath('user.points', 10)->assertJsonPath('user.pointsEarned', 10);

        // Awarded once for the day, however often it is evaluated; the dashboard lists it as held.
        $this->patchJson("/api/tasks/{$first->id}", ['title' => 'again'])->assertOk()->assertJsonCount(0, 'newAchievements');
        $this->getJson('/api/dashboard?date=2026-11-25')->assertOk()->assertJsonFragment(['key' => $badge->achievementKey(), 'timesEarned' => 1]);
        $this->assertSame(1, BadgeAward::count());

        // The next day it can be earned again, and yesterday's award no longer shows as current.
        CarbonImmutable::setTestNow('2026-11-26 10:00:00');
        Carbon::setTestNow('2026-11-26 10:00:00');
        $this->assertNotContains($badge->achievementKey(), array_column($this->getJson('/api/dashboard?date=2026-11-26')->json('achievements'), 'key'));
        $complete($this->dailyTask($user, '2026-11-26'));
        $complete($this->dailyTask($user, '2026-11-26'));
        $this->getJson('/api/auth/me')->assertJsonPath('user.points', 20);
        $this->getJson('/api/profile/points?date=2026-11-26')->assertOk()->assertJsonPath('earned', 20)
            ->assertJsonCount(30, 'history')->assertJsonPath('history.29', ['day' => '2026-11-26', 'points' => 10])->assertJsonPath('history.28.points', 10);
    }

    public function test_weekly_and_monthly_badges_count_only_their_own_period(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $weekly = Badge::create(['title' => 'أسبوع', 'points' => 50, 'period' => 'weekly', 'conditions' => [['metric' => 'perfect_days', 'threshold' => 2]]]);
        $monthly = Badge::create(['title' => 'شهر', 'points' => 100, 'period' => 'monthly', 'conditions' => [['metric' => 'perfect_days', 'threshold' => 3]]]);
        $this->dailyTask($user, '2026-11-20', 'completed');   // last week, this month
        $this->dailyTask($user, '2026-11-24', 'completed');   // this week
        $today = $this->dailyTask($user, '2026-11-25');

        $this->actingAs($user)->patchJson("/api/tasks/{$today->id}", ['status' => 'completed'])->assertOk();
        $this->assertEqualsCanonicalizing([$weekly->id, $monthly->id], BadgeAward::pluck('badge_id')->all());
        $this->assertSame(['W2026-11-23', '2026-11'], BadgeAward::orderBy('badge_id')->pluck('period_key')->all());
        $this->getJson('/api/auth/me')->assertJsonPath('user.points', 150);
    }

    public function test_members_buy_and_use_store_items_with_their_points(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $this->award($member, 120);

        $this->actingAs($member)->postJson('/api/admin/store-items', ['name' => 'x', 'kind' => 'title', 'value' => 'x', 'price' => 1])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/store-items', ['name' => 'x', 'kind' => 'frame', 'value' => 'made-up', 'price' => 1])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->actingAs($admin)->postJson('/api/admin/store-items', ['name' => 'صورة', 'kind' => 'avatar', 'price' => 1])->assertUnprocessable()->assertJsonValidationErrors('image');
        $title = $this->actingAs($admin)->postJson('/api/admin/store-items', ['name' => 'لقب الأسطورة', 'kind' => 'title', 'value' => 'الأسطورة', 'price' => 100])->assertCreated()->json('item');
        $frame = $this->actingAs($admin)->postJson('/api/admin/store-items', ['name' => 'إطار ذهبي', 'kind' => 'frame', 'value' => 'gold', 'price' => 50])->assertCreated()->json('item');
        $avatar = $this->actingAs($admin)->post('/api/admin/store-items', ['name' => 'صورة الأسد', 'kind' => 'avatar', 'price' => 10, 'image' => UploadedFile::fake()->image('lion.png', 200, 200)])->assertCreated()->json('item');
        $this->assertStringStartsWith('/media/store/', $avatar['imageUrl']);

        // Using before buying is refused; buying takes the points; buying twice or beyond the balance is refused.
        $this->actingAs($member)->getJson('/api/store')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('items.0.owned', false)->assertJsonPath('user.points', 120);
        $this->putJson('/api/store/equip', ['kind' => 'title', 'itemId' => $title['id']])->assertUnprocessable();
        $this->postJson("/api/store/{$title['id']}/buy")->assertOk()->assertJsonPath('user.points', 20)->assertJsonPath('user.pointsEarned', 120);
        $this->postJson("/api/store/{$title['id']}/buy")->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->postJson("/api/store/{$frame['id']}/buy")->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->putJson('/api/store/equip', ['kind' => 'frame', 'itemId' => $title['id']])->assertUnprocessable();

        $this->putJson('/api/store/equip', ['kind' => 'title', 'itemId' => $title['id']])->assertOk()->assertJsonPath('user.title', 'الأسطورة')->assertJsonPath('user.frame', 'none')->assertJsonPath('user.theme', 'gold');
        $this->postJson("/api/store/{$avatar['id']}/buy")->assertOk()->assertJsonPath('user.points', 10);
        $this->putJson('/api/store/equip', ['kind' => 'avatar', 'itemId' => $avatar['id']])->assertOk()->assertJsonPath('user.avatarUrl', $avatar['imageUrl']);
        // The profile form sends the photo back unchanged; a store image must pass its validation.
        $this->putJson('/api/profile', ['displayName' => 'نورا', 'surname' => '', 'nickname' => '', 'bio' => '', 'timezone' => 'UTC', 'language' => 'ar', 'weeklyGoal' => 5, 'dailyFocusMinutes' => 30, 'avatarUrl' => $avatar['imageUrl']])->assertOk();
        $this->putJson('/api/store/equip', ['kind' => 'title', 'itemId' => null])->assertOk()->assertJsonPath('user.title', null);

        // An item taken off sale stays with its owner but can no longer be bought; a deleted one gives the points back.
        $this->actingAs($admin)->postJson("/api/admin/store-items/{$frame['id']}", ['isActive' => false])->assertOk();
        $this->actingAs($member)->getJson('/api/store')->assertJsonCount(2, 'items');
        $this->postJson("/api/store/{$frame['id']}/buy")->assertUnprocessable();
        $this->actingAs($admin)->deleteJson("/api/admin/store-items/{$title['id']}")->assertNoContent();
        $this->actingAs($member)->getJson('/api/auth/me')->assertJsonPath('user.points', 110);
        $this->assertSame(0, StoreItem::where('id', $title['id'])->count());
    }

    public function test_the_leaderboard_ranks_today_the_week_and_points(): void
    {
        [$sara, $omar, $idle] = [User::factory()->create(['display_name' => 'سارة', 'timezone' => 'UTC']), User::factory()->create(['display_name' => 'عمر', 'timezone' => 'UTC']), User::factory()->create()];
        // Sara: two perfect days this week, all of today's tasks done. Omar: more tasks today, but one left open.
        $this->dailyTask($sara, '2026-11-24', 'completed');
        $this->dailyTask($sara, '2026-11-25', 'completed');
        foreach (range(1, 3) as $i) {
            $this->dailyTask($omar, '2026-11-25', 'completed');
        }
        $this->dailyTask($omar, '2026-11-25');
        $this->dailyTask($omar, '2026-11-10', 'completed');   // earlier this month, not this week
        $this->award($omar, 300, '2026-10-01');
        $this->award($sara, 80);

        $board = $this->actingAs($idle)->getJson('/api/leaderboard?date=2026-11-25')->assertOk()->json();
        $this->assertSame([['عمر', 3, false], ['سارة', 1, true]], array_map(fn ($row) => [$row['displayName'], $row['value'], $row['allDone']], $board['today']));
        $this->assertSame([['سارة', 2], ['عمر', 0]], array_map(fn ($row) => [$row['displayName'], $row['value']], $board['week']));
        $this->assertSame([['عمر', 300], ['سارة', 80]], array_map(fn ($row) => [$row['displayName'], $row['value']], $board['points']));
        $this->assertSame([['سارة', 80]], array_map(fn ($row) => [$row['displayName'], $row['value']], $board['month']));
        $this->assertArrayNotHasKey('email', $board['today'][0]);
    }

    public function test_admins_schedule_and_send_messages_that_members_receive_on_the_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['timezone' => 'UTC']);

        $this->actingAs($member)->postJson('/api/admin/broadcasts', ['body' => 'x', 'day' => '2026-11-25'])->assertForbidden();
        $this->actingAs($admin)->putJson('/api/admin/broadcasts/schedule/2026-11-25', ['body' => 'رسالة الأربعاء'])->assertNoContent();
        $this->putJson('/api/admin/broadcasts/schedule/2026-11-26', ['body' => 'رسالة الخميس'])->assertNoContent();
        $this->putJson('/api/admin/broadcasts/schedule/2026-11-25', ['body' => 'رسالة الأربعاء المعدّلة'])->assertNoContent();
        $this->getJson('/api/admin/broadcasts?from=2026-11-23')->assertOk()
            ->assertJsonPath('scheduled', ['2026-11-25' => 'رسالة الأربعاء المعدّلة', '2026-11-26' => 'رسالة الخميس'])->assertJsonCount(0, 'instant');

        // Members get today's message only; tomorrow's waits for tomorrow.
        $this->actingAs($member)->getJson('/api/dashboard?date=2026-11-25')->assertJsonCount(1, 'broadcasts')->assertJsonPath('broadcasts.0.body', 'رسالة الأربعاء المعدّلة');
        $id = $this->actingAs($admin)->postJson('/api/admin/broadcasts', ['body' => 'تنبيه عاجل', 'day' => '2026-11-25'])->assertCreated()->json('id');
        $this->actingAs($member)->getJson('/api/messages/unread')->assertJsonCount(2, 'broadcasts')->assertJsonPath('broadcasts.1.kind', 'instant');

        $this->actingAs($admin)->putJson('/api/admin/broadcasts/schedule/2026-11-25', ['body' => ''])->assertNoContent();
        $this->deleteJson("/api/admin/broadcasts/{$id}")->assertNoContent();
        $this->assertSame(1, Broadcast::count());
    }
}
