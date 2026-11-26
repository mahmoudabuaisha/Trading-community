<?php

namespace Tests\Feature;

use App\Models\DailyTip;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTipAndAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_member_draws_one_tip_a_day_for_free(): void
    {
        DailyTip::query()->delete();
        $member = User::factory()->create();
        $this->actingAs($member)->getJson('/api/daily-tip?date=2026-11-26')->assertOk()->assertJsonPath('tip', null)->assertJsonPath('available', false);
        $this->postJson('/api/daily-tip', ['date' => '2026-11-26'])->assertUnprocessable();

        foreach (range(1, 20) as $i) {
            DailyTip::create(['body' => "نصيحة {$i}"]);
        }
        DailyTip::create(['body' => 'مخفية', 'is_active' => false]);
        $this->getJson('/api/daily-tip?date=2026-11-26')->assertJsonPath('available', true);

        $drawn = $this->postJson('/api/daily-tip', ['date' => '2026-11-26'])->assertOk()->assertJsonPath('available', false)->json();
        $this->assertStringStartsWith('نصيحة', $drawn['tip']);
        // The reel ends on the drawn line and never shows a hidden one.
        $this->assertSame($drawn['tip'], end($drawn['reel']));
        $this->assertNotContains('مخفية', $drawn['reel']);
        $this->assertCount(15, $drawn['reel']);

        // Drawing again the same day changes nothing; the line stays until the day turns.
        $this->postJson('/api/daily-tip', ['date' => '2026-11-26'])->assertOk()->assertJsonPath('tip', $drawn['tip']);
        $this->getJson('/api/daily-tip?date=2026-11-26')->assertJsonPath('tip', $drawn['tip'])->assertJsonPath('available', false);
        $this->getJson('/api/daily-tip?date=2026-11-27')->assertJsonPath('tip', null)->assertJsonPath('available', true);
        $this->assertSame(1, $member->dailyTipDraws()->count());

        // Points and subscriptions are untouched.
        $this->getJson('/api/auth/me')->assertJsonPath('user.points', 0)->assertJsonPath('user.isPremium', false);
        // If the admin deletes the line, the member still sees what they drew today.
        DailyTip::query()->delete();
        $this->getJson('/api/daily-tip?date=2026-11-26')->assertJsonPath('tip', $drawn['tip']);
    }

    public function test_admins_add_many_tips_at_once_and_manage_them(): void
    {
        DailyTip::query()->delete();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs(User::factory()->create())->postJson('/api/admin/daily-tips', ['bodies' => ['x']])->assertForbidden();
        $tips = $this->actingAs($admin)->postJson('/api/admin/daily-tips', ['bodies' => ['الأولى', ' الثانية ', 'الأولى', '']])->assertCreated()->json('tips');
        $this->assertSame(['الثانية', 'الأولى'], array_column($tips, 'body'));
        $this->patchJson("/api/admin/daily-tips/{$tips[0]['id']}", ['body' => 'معدّلة', 'isActive' => false])->assertOk()->assertJsonPath('tips.0.body', 'معدّلة')->assertJsonPath('tips.0.isActive', false);
        $this->deleteJson("/api/admin/daily-tips/{$tips[1]['id']}")->assertNoContent();
        $this->getJson('/api/admin/daily-tips')->assertJsonCount(1, 'tips');
    }

    public function test_members_add_their_own_life_areas_which_count_like_the_others(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonCount(4, 'categories');

        $area = $this->postJson('/api/categories', ['name' => 'الرياضة', 'color' => '#e58a7b'])->assertCreated()->json('category');
        $this->postJson('/api/categories', ['name' => 'الرياضة', 'color' => '#e58a7b'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/categories', ['name' => 'x', 'color' => 'red'])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->actingAs(User::factory()->create())->patchJson("/api/categories/{$area['id']}", ['name' => 'y', 'color' => '#000000'])->assertNotFound();

        // A task filed under the new area counts towards it in the statistics.
        Task::factory()->for($member)->create(['category_id' => $area['id'], 'status' => 'completed', 'completed_at' => now(), 'created_at' => now()]);
        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonCount(5, 'categories');
        $insights = $this->getJson('/api/insights')->json('byCategory');
        $this->assertContains(['name' => 'الرياضة', 'total' => 1, 'done' => 1], $insights);

        $this->patchJson("/api/categories/{$area['id']}", ['name' => 'اللياقة', 'color' => '#79cda4'])->assertOk()->assertJsonPath('category.name', 'اللياقة');
        $this->deleteJson("/api/categories/{$area['id']}")->assertNoContent();
        $this->assertNull(Task::first()->category_id);
    }
}
