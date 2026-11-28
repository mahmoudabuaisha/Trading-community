<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\Design;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignsTest extends TestCase
{
    use RefreshDatabase;

    private function givePoints(User $user, int $points): void
    {
        $badge = Badge::create(['title' => 'x', 'points' => $points, 'period' => 'once', 'conditions' => [['metric' => 'tasks_completed', 'threshold' => 999999]]]);
        BadgeAward::create(['user_id' => $user->id, 'badge_id' => $badge->id, 'period_key' => 'once', 'points' => $points, 'day' => now()->toDateString()]);
    }

    public function test_two_free_designs_per_target_and_paid_ones_bought_with_points(): void
    {
        $member = User::factory()->create();
        $response = $this->actingAs($member)->getJson('/api/designs')->assertOk();
        $designs = collect($response->json('designs'));
        foreach (Design::TARGETS as $target) {
            // Two free designs per target, plus the free hierarchy for skill trees.
            $this->assertCount($target === 'skill_tree' ? 3 : 2, $designs->where('target', $target)->where('price', 0), $target);
        }
        // With nothing chosen, the first free design of each target is in use.
        $freeMap = $designs->where('target', 'goal_map')->where('price', 0)->first();
        $this->assertSame($freeMap['id'], $response->json('selected.goal_map'));

        $paid = $designs->where('target', 'skill_tree')->where('price', '>', 0)->first();
        $this->putJson('/api/designs/select', ['designId' => $paid['id']])->assertUnprocessable();
        $this->postJson("/api/designs/{$paid['id']}/buy")->assertUnprocessable();
        $this->postJson("/api/designs/{$freeMap['id']}/buy")->assertUnprocessable();

        $this->givePoints($member, $paid['price'] + 30);
        $this->postJson("/api/designs/{$paid['id']}/buy")->assertOk()->assertJsonPath('selected.skill_tree', $paid['id'])->assertJsonPath('user.points', 30);
        $this->postJson("/api/designs/{$paid['id']}/buy")->assertUnprocessable();
        $other = $designs->where('target', 'skill_tree')->where('price', 0)->last();
        $this->putJson('/api/designs/select', ['designId' => $other['id']])->assertOk()->assertJsonPath('selected.skill_tree', $other['id']);
        $this->putJson('/api/designs/select', ['designId' => $paid['id']])->assertOk()->assertJsonPath('selected.skill_tree', $paid['id']);

        // Deleting a bought design refunds it and falls back to a free one.
        Design::find($paid['id'])->delete();
        $this->getJson('/api/designs')->assertJsonPath('user.points', $paid['price'] + 30)
            ->assertJsonPath('selected.skill_tree', $designs->where('target', 'skill_tree')->where('price', 0)->first()['id']);
    }

    public function test_admins_dress_a_built_in_layout_as_a_new_design(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs(User::factory()->create())->postJson('/api/admin/designs', [])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/designs', ['target' => 'goal_map', 'layout' => 'pyramid', 'name' => 'x', 'price' => 10, 'accent' => '#ffffff', 'nodeShape' => 'star'])
            ->assertUnprocessable()->assertJsonValidationErrors('layout');
        $this->postJson('/api/admin/designs', ['target' => 'skill_tree', 'layout' => 'pyramid', 'name' => 'x', 'price' => 10, 'accent' => 'red', 'nodeShape' => 'blob'])
            ->assertUnprocessable()->assertJsonValidationErrors(['accent', 'nodeShape']);
        $designs = $this->post('/api/admin/designs', ['target' => 'skill_tree', 'layout' => 'pyramid', 'name' => 'هرم الليل', 'price' => 75, 'accent' => '#8fb6ff', 'nodeShape' => 'star', 'background' => UploadedFile::fake()->image('bg.jpg', 400, 300)])
            ->assertCreated()->assertJsonPath('layouts.skill_tree', ['hierarchy', 'tree', 'pyramid', 'stairs'])->json('designs');
        $new = collect($designs)->firstWhere('name', 'هرم الليل');
        $this->assertStringStartsWith('/media/designs/', $new['backgroundUrl']);
        $this->post("/api/admin/designs/{$new['id']}", ['price' => 90, 'isActive' => '0'])->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/api/designs')->assertJsonMissing(['name' => 'هرم الليل']);
        $this->actingAs($admin)->deleteJson("/api/admin/designs/{$new['id']}")->assertNoContent();
        $this->assertSame([], Storage::disk('public')->allFiles('designs'));
    }

    public function test_a_goal_map_design_can_dress_a_skill_tree(): void
    {
        $member = User::factory()->create();
        $map = Design::where('target', 'goal_map')->where('price', 0)->firstOrFail();
        $this->actingAs($member)->putJson('/api/designs/select', ['designId' => $map->id, 'target' => 'skill_tree'])
            ->assertOk()->assertJsonPath('selected.skill_tree', $map->id);
    }
}
