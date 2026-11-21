<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_task_is_awarded_exactly_once(): void
    {
        $user = User::factory()->create();
        [$first, $second] = Task::factory()->count(2)->for($user)->create();
        $this->actingAs($user);

        $this->patchJson("/api/tasks/{$first->id}", ['status' => 'completed'])
            ->assertOk()->assertJsonCount(1, 'newAchievements')->assertJsonPath('newAchievements.0.key', 'first_task');

        $this->patchJson("/api/tasks/{$second->id}", ['status' => 'completed'])
            ->assertOk()->assertJsonCount(0, 'newAchievements');

        // Un-completing and re-completing never duplicates the badge.
        $this->patchJson("/api/tasks/{$first->id}", ['status' => 'pending']);
        $this->patchJson("/api/tasks/{$first->id}", ['status' => 'completed'])->assertJsonCount(0, 'newAchievements');

        $this->assertSame(1, $user->achievements()->where('key', 'first_task')->count());
    }

    public function test_seven_day_streak_is_awarded_in_the_users_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Gaza']);
        $now = CarbonImmutable::parse('2026-11-21 10:00', 'Asia/Gaza');
        $this->travelTo($now);

        // Six earlier days, each completed late in the local evening (the next day in UTC+… is irrelevant: local day counts).
        foreach (range(1, 6) as $daysAgo) {
            $this->travelTo($now->subDays($daysAgo)->setTime(23, 30));
            Task::factory()->for($user)->completed()->create();
        }
        $this->travelTo($now);

        $service = app(AchievementService::class);
        $this->assertSame(6, $service->currentStreak($user));
        $this->assertNotContains('streak_7', array_map(fn ($a) => $a->key, $service->evaluate($user)));

        $today = Task::factory()->for($user)->create();
        $response = $this->actingAs($user)->patchJson("/api/tasks/{$today->id}", ['status' => 'completed'])->assertOk();

        $this->assertContains('streak_7', array_column($response->json('newAchievements'), 'key'));
        $this->assertSame(7, $service->currentStreak($user));
        $this->assertSame([], $service->evaluate($user));
    }

    public function test_a_gap_breaks_the_streak(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $now = CarbonImmutable::parse('2026-11-21 12:00', 'UTC');

        foreach ([0, 1, 3, 4] as $daysAgo) {
            $this->travelTo($now->subDays($daysAgo));
            Task::factory()->for($user)->completed()->create();
        }
        $this->travelTo($now);

        $this->assertSame(2, app(AchievementService::class)->currentStreak($user));
    }

    public function test_goal_and_focus_milestones(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['target_value' => 1]);
        $this->actingAs($user);

        $this->patchJson("/api/goals/{$goal->id}", ['currentValue' => 1])
            ->assertOk()->assertJsonPath('newAchievements.0.key', 'first_goal');

        $this->postJson('/api/focus-sessions', ['minutes' => 590, 'day' => '2026-11-21'])
            ->assertCreated()->assertJsonCount(0, 'newAchievements')
            ->assertJsonPath('focus.totalMinutes', 590)->assertJsonPath('focus.todayMinutes', 590);

        $this->postJson('/api/focus-sessions', ['minutes' => 25, 'day' => '2026-11-22'])
            ->assertCreated()->assertJsonPath('newAchievements.0.key', 'focus_600')
            ->assertJsonPath('focus.totalMinutes', 615)->assertJsonPath('focus.todayMinutes', 25);

        $this->postJson('/api/focus-sessions', ['minutes' => 0, 'day' => '2026-11-22'])->assertStatus(422);
    }
}
