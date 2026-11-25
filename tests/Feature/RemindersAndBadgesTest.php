<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\AdminAudit;
use App\Models\Badge;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemindersAndBadgesTest extends TestCase
{
    use RefreshDatabase;

    private function taskPayload(array $extra = []): array
    {
        return ['title' => 'مهمة', 'periodType' => 'daily', 'periodStart' => '2026-11-25', 'periodEnd' => '2026-11-25', ...$extra];
    }

    public function test_a_task_reminder_can_be_set_dismissed_and_reset(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = $this->postJson('/api/tasks', $this->taskPayload(['remindAt' => '2026-11-25T15:30:00.000Z']))
            ->assertCreated()->assertJsonPath('task.remindAt', '2026-11-25T15:30:00.000000Z')->assertJsonPath('task.reminderDismissedAt', null)->json('task');

        $this->postJson("/api/tasks/{$task['id']}/dismiss-reminder")->assertOk();
        $this->assertNotNull(Task::find($task['id'])->reminder_dismissed_at);

        // Editing other fields keeps it dismissed; a new time makes it a fresh reminder.
        $this->patchJson("/api/tasks/{$task['id']}", ['title' => 'معدّلة'])->assertOk();
        $this->assertNotNull(Task::find($task['id'])->reminder_dismissed_at);
        $this->patchJson("/api/tasks/{$task['id']}", ['remindAt' => '2026-11-26T08:00:00Z'])->assertOk()->assertJsonPath('task.reminderDismissedAt', null);

        $this->patchJson("/api/tasks/{$task['id']}", ['remindAt' => null])->assertOk()->assertJsonPath('task.remindAt', null);
        $this->patchJson("/api/tasks/{$task['id']}", ['remindAt' => 'not a date'])->assertUnprocessable()->assertJsonValidationErrors('remindAt');
    }

    public function test_only_the_owner_can_dismiss_a_reminder(): void
    {
        $task = Task::factory()->for(User::factory())->create(['remind_at' => now()]);

        $this->actingAs(User::factory()->create())->postJson("/api/tasks/{$task->id}/dismiss-reminder")->assertForbidden();
        $this->assertNull($task->refresh()->reminder_dismissed_at);
    }

    public function test_admin_defined_badges_are_awarded_once_when_the_threshold_is_reached(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();

        $badge = $this->actingAs($admin)->postJson('/api/admin/badges', ['title' => 'ثلاث مهام', 'description' => 'أكمل 3 مهام', 'metric' => 'tasks_completed', 'threshold' => 3])
            ->assertCreated()->assertJsonPath('badge.isActive', true)->json('badge');
        $this->assertSame("badge:{$badge['id']}", $badge['key']);
        $this->actingAs($admin)->postJson('/api/admin/badges', ['title' => 'x', 'metric' => 'nonsense', 'threshold' => 1])->assertUnprocessable()->assertJsonValidationErrors('metric');
        $this->actingAs($member)->postJson('/api/admin/badges', ['title' => 'x', 'metric' => 'tasks_completed', 'threshold' => 1])->assertForbidden();

        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonCount(1, 'badges')->assertJsonPath('badges.0.title', 'ثلاث مهام');

        $tasks = Task::factory()->count(3)->for($member)->create();
        $complete = fn (Task $task) => $this->actingAs($member)->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertOk();
        $keys = fn ($response) => collect($response->json('newAchievements'))->pluck('key')->all();

        $this->assertNotContains($badge['key'], $keys($complete($tasks[0])));
        $this->assertNotContains($badge['key'], $keys($complete($tasks[1])));
        $this->assertContains($badge['key'], $keys($complete($tasks[2])));
        // Evaluating again never awards it twice.
        $this->actingAs($member)->patchJson("/api/tasks/{$tasks[2]->id}", ['title' => 'again'])->assertOk()->assertJsonCount(0, 'newAchievements');
        $this->assertSame(1, Achievement::where('key', $badge['key'])->count());

        // An inactive badge is hidden and not awarded; deleting a badge removes its awards.
        $hidden = $this->actingAs($admin)->postJson('/api/admin/badges', ['title' => 'مخفية', 'metric' => 'tasks_completed', 'threshold' => 1, 'isActive' => false])->json('badge');
        $this->actingAs($member)->patchJson("/api/tasks/{$tasks[0]->id}", ['title' => 'x'])->assertJsonCount(0, 'newAchievements');
        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonCount(1, 'badges');
        $this->assertSame(0, Achievement::where('key', $hidden['key'])->count());

        $this->actingAs($admin)->deleteJson("/api/admin/badges/{$badge['id']}")->assertNoContent();
        $this->assertSame(0, Achievement::where('key', $badge['key'])->count());
        $this->assertSame(['badge_created', 'badge_created', 'badge_deleted'], AdminAudit::orderBy('id')->pluck('action')->all());
        $this->assertSame(1, Badge::count());
    }

    public function test_habit_checks_can_earn_a_badge(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $badge = Badge::create(['title' => 'عادتان', 'metric' => 'habit_checks', 'threshold' => 2]);
        $habit = $member->habits()->create(['name' => 'ماء']);

        $this->actingAs($member)->putJson("/api/habits/{$habit->id}/checks/2026-11-01", ['done' => true])->assertNoContent();
        $this->assertSame(0, $member->achievements()->count());
        $this->actingAs($member)->putJson("/api/habits/{$habit->id}/checks/2026-11-02", ['done' => true])->assertNoContent();
        $this->assertSame([$badge->achievementKey()], $member->achievements()->pluck('key')->all());
    }
}
