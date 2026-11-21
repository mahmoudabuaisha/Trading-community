<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_is_created_active_with_zero_progress(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/goals', [
            'title' => 'قراءة 5 فصول',
            'periodType' => 'weekly',
            'periodStart' => '2026-11-16',
            'periodEnd' => '2026-11-22',
            'targetValue' => 5,
        ])->assertCreated()->assertJson(['goal' => [
            'title' => 'قراءة 5 فصول', 'description' => '', 'targetValue' => 5, 'currentValue' => 0, 'status' => 'active',
            'periodStart' => '2026-11-16', 'periodEnd' => '2026-11-22',
        ]]);
    }

    public function test_progress_is_clamped_and_status_is_derived(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['target_value' => 3]);
        $this->actingAs($user);

        $this->patchJson("/api/goals/{$goal->id}", ['currentValue' => 2])
            ->assertOk()->assertJsonPath('goal.currentValue', 2)->assertJsonPath('goal.status', 'active');

        $this->patchJson("/api/goals/{$goal->id}", ['currentValue' => 99])
            ->assertOk()->assertJsonPath('goal.currentValue', 3)->assertJsonPath('goal.status', 'completed');

        $this->patchJson("/api/goals/{$goal->id}", ['currentValue' => 1])
            ->assertOk()->assertJsonPath('goal.status', 'active');

        $this->patchJson("/api/goals/{$goal->id}", ['currentValue' => -4])->assertStatus(422);
    }

    public function test_model_enforces_the_invariant_outside_the_api(): void
    {
        $goal = Goal::factory()->create(['target_value' => 2, 'current_value' => 50]);

        $this->assertSame(2, $goal->current_value);
        $this->assertSame('completed', $goal->status);
    }

    public function test_goals_are_private_to_their_owner(): void
    {
        $goal = Goal::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->patchJson("/api/goals/{$goal->id}", ['currentValue' => 1])->assertForbidden();
        $this->deleteJson("/api/goals/{$goal->id}")->assertForbidden();
        $this->assertModelExists($goal);
    }
}
