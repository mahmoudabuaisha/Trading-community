<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'تحليل افتتاح الذهب',
            'description' => '',
            'periodType' => 'daily',
            'periodStart' => '2026-11-21',
            'periodEnd' => '2026-11-21',
            'dueDate' => '2026-11-21',
            'priority' => 'high',
            'categoryId' => null,
        ];
    }

    public function test_user_creates_a_task_in_the_contract_shape(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->first();

        $this->actingAs($user)->postJson('/api/tasks', $this->payload(['categoryId' => $category->id]))
            ->assertCreated()
            ->assertJson(['task' => [
                'title' => 'تحليل افتتاح الذهب',
                'periodType' => 'daily',
                'periodStart' => '2026-11-21',
                'periodEnd' => '2026-11-21',
                'dueDate' => '2026-11-21',
                'priority' => 'high',
                'status' => 'pending',
                'categoryId' => $category->id,
                'completedAt' => null,
            ]]);

        $this->assertSame($user->id, Task::firstOrFail()->user_id);
    }

    public function test_task_validation_rejects_bad_values(): void
    {
        $user = User::factory()->create();
        $foreignCategory = User::factory()->create()->categories()->first();

        $this->actingAs($user)->postJson('/api/tasks', $this->payload([
            'title' => '',
            'periodType' => 'hourly',
            'periodEnd' => '2026-11-20',
            'priority' => 'critical',
            'categoryId' => $foreignCategory->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['title', 'periodType', 'periodEnd', 'priority', 'categoryId']);

        $this->assertSame(0, Task::count());
    }

    public function test_a_user_cannot_see_update_or_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = Task::factory()->for($owner)->create(['title' => 'private']);

        $this->actingAs($intruder);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonCount(0, 'tasks');
        $this->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertForbidden();
        $this->patchJson("/api/tasks/{$task->id}", ['title' => 'hacked'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

        $task->refresh();
        $this->assertSame('private', $task->title);
        $this->assertSame('pending', $task->status);
    }

    public function test_completed_at_follows_the_status(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $done = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertOk();
        $this->assertNotNull($done->json('task.completedAt'));
        $this->assertNotNull($task->fresh()->completed_at);

        // Editing a completed task keeps its original completion instant.
        $stamp = $task->fresh()->completed_at;
        $this->travel(2)->hours();
        $this->patchJson("/api/tasks/{$task->id}", ['title' => 'renamed'])->assertOk();
        $this->assertTrue($stamp->equalTo($task->fresh()->completed_at));

        $this->patchJson("/api/tasks/{$task->id}", ['status' => 'pending'])
            ->assertOk()->assertJsonPath('task.completedAt', null);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_owner_can_delete_a_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
        $this->assertModelMissing($task);
        $this->deleteJson('/api/tasks/999999')->assertNotFound();
    }
}
