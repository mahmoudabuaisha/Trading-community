<?php

namespace Tests\Feature;

use App\Models\SkillPath;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MySkillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_build_their_own_skill_paths_privately(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        SkillPath::create(['title' => 'مسار المنصة', 'is_active' => true]);

        $path = $this->actingAs($member)->postJson('/api/my-skills', ['title' => 'البرمجة', 'description' => 'هدفي هذا العام'])->assertCreated()->json('paths.0');
        $paths = $this->postJson("/api/my-skills/{$path['id']}/levels", ['title' => 'أساسيات PHP', 'description' => "كورس + تمارين\nساعة يومياً"])->assertCreated()->json('paths');
        $paths = $this->postJson("/api/my-skills/{$path['id']}/levels", ['title' => 'Laravel'])->assertCreated()->json('paths');
        $second = $paths[0]['levels'][1];
        // Any order: the second skill can be ticked first.
        $this->postJson("/api/my-skills/{$path['id']}/levels/{$second['id']}/toggle")->assertOk()->assertJsonPath('paths.0.levels.1.passed', true)->assertJsonPath('paths.0.levels.0.passed', false);

        // The platform's paths stay separate, and nobody else sees or edits a member's path.
        $this->getJson('/api/skills')->assertOk()->assertJsonCount(1, 'paths')->assertJsonPath('paths.0.title', 'مسار المنصة');
        $this->actingAs($other)->getJson('/api/my-skills')->assertOk()->assertJsonCount(0, 'paths');
        $this->patchJson("/api/my-skills/{$path['id']}", ['title' => 'x'])->assertNotFound();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/admin/skill-paths')->assertOk()->assertJsonCount(1, 'paths');
        $this->patchJson("/api/admin/skill-paths/{$path['id']}", ['title' => 'x'])->assertNotFound();

        $this->actingAs($member)->deleteJson("/api/my-skills/{$path['id']}")->assertOk()->assertJsonCount(0, 'paths');
    }
}
