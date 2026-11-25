<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\SkillLevel;
use App\Models\SkillPath;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GrowthJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-25 10:00:00');   // a Wednesday
        Carbon::setTestNow('2026-11-25 10:00:00');
    }

    private function goal(User $user): Goal
    {
        return $user->goals()->create(['title' => 'حساب ممول', 'description' => '', 'period_type' => 'yearly', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'target_value' => 1]);
    }

    public function test_members_choose_their_specialties(): void
    {
        $user = User::factory()->create();
        $profile = ['displayName' => 'نورا', 'surname' => '', 'bio' => '', 'timezone' => 'UTC', 'language' => 'ar', 'weeklyGoal' => 5, 'dailyFocusMinutes' => 30, 'avatarUrl' => null];

        $this->actingAs($user)->putJson('/api/profile', [...$profile, 'specialties' => ['student_university', 'trader_beginner']])
            ->assertOk()->assertJsonPath('user.specialties', ['student_university', 'trader_beginner']);
        $this->putJson('/api/profile', [...$profile, 'specialties' => ['astronaut']])->assertUnprocessable()->assertJsonValidationErrors('specialties.0');
        // Saving the profile without the field leaves the choice alone.
        $this->putJson('/api/profile', $profile)->assertOk()->assertJsonPath('user.specialties', ['student_university', 'trader_beginner']);
    }

    public function test_insights_find_the_strongest_day_and_time_and_compare_periods(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $done = fn (string $at, array $extra = []) => Task::factory()->for($user)->create(['status' => 'completed', 'completed_at' => $at, 'created_at' => $at, ...$extra]);
        // Six finished on Monday mornings in the last 30 days, two on a Friday night in the 30 before.
        foreach (['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23'] as $monday) {
            $done($monday.' 08:30:00');
        }
        $done('2026-11-23 09:10:00');
        $done('2026-11-16 10:00:00');
        $done('2026-10-09 23:00:00');
        $done('2026-10-09 22:30:00');
        Task::factory()->for($user)->create(['status' => 'pending', 'created_at' => '2026-11-20 08:00:00']);

        $this->actingAs($user)->getJson('/api/insights?date=2026-11-25')->assertOk()
            ->assertJsonPath('enoughData', true)->assertJsonPath('totalCompleted', 8)
            ->assertJsonPath('bestWeekday', 0)->assertJsonPath('bestPart', 'morning')
            ->assertJsonPath('byWeekday.0', 6)->assertJsonPath('byWeekday.4', 2)->assertJsonPath('byHour.8', 4)
            ->assertJsonPath('current.tasksCompleted', 6)->assertJsonPath('previous.tasksCompleted', 2)
            ->assertJsonPath('current.completionRate', 86)->assertJsonCount(12, 'weeks')
            ->assertJsonFragment(['key' => 'bestDay', 'params' => ['weekday' => 0]]);

        // Too little to go on: no conclusions, no advice.
        $this->actingAs(User::factory()->create())->getJson('/api/insights?date=2026-11-25')->assertOk()
            ->assertJsonPath('enoughData', false)->assertJsonPath('bestWeekday', null)->assertJsonCount(0, 'tips');
    }

    public function test_a_goal_roadmap_tracks_stages_steps_and_order(): void
    {
        $user = User::factory()->create();
        $goal = $this->goal($user);
        $this->actingAs(User::factory()->create())->getJson("/api/goals/{$goal->id}/steps")->assertNotFound();

        $stage = $this->actingAs($user)->postJson("/api/goals/{$goal->id}/steps", ['title' => 'التعلم', 'requirements' => 'إنهاء الدورة'])->assertCreated()->json('step');
        $first = $this->postJson("/api/goals/{$goal->id}/steps", ['title' => 'أساسيات', 'parentId' => $stage['id']])->assertCreated()->json('step');
        $second = $this->postJson("/api/goals/{$goal->id}/steps", ['title' => 'إدارة المخاطر', 'parentId' => $stage['id']])->assertCreated()->json('step');
        // Steps hang under stages only, never under another step.
        $this->postJson("/api/goals/{$goal->id}/steps", ['title' => 'x', 'parentId' => $first['id']])->assertNotFound();

        $byId = fn ($response) => collect($response->json('steps'))->keyBy('id');
        $steps = $byId($this->patchJson("/api/goal-steps/{$first['id']}", ['isDone' => true])->assertOk());
        $this->assertFalse($steps[$stage['id']]['isDone']);
        // The stage completes itself when its last step does, and reopens with it.
        $steps = $byId($this->patchJson("/api/goal-steps/{$second['id']}", ['isDone' => true])->assertOk());
        $this->assertTrue($steps[$stage['id']]['isDone']);
        $steps = $byId($this->patchJson("/api/goal-steps/{$second['id']}", ['isDone' => false])->assertOk());
        $this->assertFalse($steps[$stage['id']]['isDone']);

        $ordered = collect($this->patchJson("/api/goal-steps/{$second['id']}", ['move' => 'up'])->assertOk()->json('steps'))->where('parentId', $stage['id'])->pluck('title')->all();
        $this->assertSame(['إدارة المخاطر', 'أساسيات'], $ordered);
        $this->deleteJson("/api/goal-steps/{$stage['id']}")->assertNoContent();
        $this->getJson("/api/goals/{$goal->id}/steps")->assertJsonCount(0, 'steps');
    }

    public function test_the_timeline_collects_milestones_and_keeps_personal_notes(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->for($user)->create();
        $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $events = collect($this->getJson('/api/timeline')->assertOk()->assertJsonPath('stages.0', ['month' => '2026-11', 'tasks' => 1, 'points' => 0])->json('events'))->keyBy('key');
        $this->assertSame(['achievement:first_task', 'joined'], $events->keys()->sort()->values()->all());

        $this->putJson('/api/timeline/notes/achievement:first_task', ['note' => 'أول خطوة'])->assertNoContent();
        $id = $this->postJson('/api/timeline', ['title' => 'أول صفقة رابحة', 'day' => '2026-11-20', 'note' => 'تعلمت الصبر'])->assertCreated()->json('id');
        $this->postJson('/api/timeline', ['title' => '', 'day' => 'x'])->assertUnprocessable();
        $events = collect($this->getJson('/api/timeline')->json('events'))->keyBy('key');
        $this->assertSame('أول خطوة', $events['achievement:first_task']['note']);
        $this->assertSame('أول صفقة رابحة', $events["custom:{$id}"]['title']);

        $this->actingAs(User::factory()->create())->deleteJson("/api/timeline/{$id}")->assertNotFound();
        $this->actingAs($user)->putJson('/api/timeline/notes/achievement:first_task', ['note' => ''])->assertNoContent();
        $this->deleteJson("/api/timeline/{$id}")->assertNoContent();
        $this->assertSame(0, $user->timelineEntries()->count());
    }

    public function test_vision_boards_hold_images_quotes_and_linked_goals_in_a_chosen_order(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $goal = $this->goal($user);

        $board = $this->actingAs($user)->postJson('/api/vision-boards', ['title' => 'مستقبلي المالي', 'area' => 'finance', 'color' => 'emerald'])->assertCreated()->json('board');
        $this->postJson('/api/vision-boards', ['title' => 'x', 'color' => 'neon'])->assertUnprocessable();
        $this->postJson("/api/vision-boards/{$board['id']}/items", ['kind' => 'quote', 'text' => 'الصبر مفتاح الربح'])->assertCreated();
        $this->postJson("/api/vision-boards/{$board['id']}/items", ['kind' => 'dream', 'text' => 'حساب ممول', 'goalId' => $goal->id])->assertCreated()->assertJsonPath('board.items.1.goalId', $goal->id);
        $this->postJson("/api/vision-boards/{$board['id']}/items", ['kind' => 'dream', 'text' => 'x', 'goalId' => $this->goal($other)->id])->assertNotFound();
        $this->postJson("/api/vision-boards/{$board['id']}/items", ['kind' => 'image'])->assertUnprocessable()->assertJsonValidationErrors('image');
        $items = $this->post("/api/vision-boards/{$board['id']}/items", ['kind' => 'image', 'image' => UploadedFile::fake()->image('car.jpg', 300, 200)])->assertCreated()->json('board.items');
        $this->assertStringStartsWith('/media/vision/', $items[2]['imageUrl']);

        $reordered = $this->patchJson("/api/vision-boards/{$board['id']}", ['order' => [$items[2]['id'], $items[0]['id'], $items[1]['id']]])->assertOk()->json('board.items');
        $this->assertSame(['image', 'quote', 'dream'], array_column($reordered, 'kind'));
        $this->patchJson("/api/vision-items/{$items[0]['id']}", ['size' => 'lg', 'text' => 'نص جديد'])->assertOk();

        $this->actingAs($other)->getJson('/api/vision-boards')->assertJsonCount(0, 'boards');
        $this->actingAs($other)->patchJson("/api/vision-boards/{$board['id']}", ['title' => 'x'])->assertNotFound();
        $this->actingAs($other)->deleteJson("/api/vision-items/{$items[0]['id']}")->assertNotFound();
        $this->actingAs($user)->deleteJson("/api/vision-boards/{$board['id']}")->assertNoContent();
        $this->assertSame([], Storage::disk('public')->allFiles('vision'));
    }

    public function test_skill_levels_open_in_order_and_quizzes_never_leak_their_answers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $quiz = [['question' => 'ما هو وقف الخسارة؟', 'options' => ['أمر حماية', 'نوع شمعة'], 'answer' => 0], ['question' => 'نسبة المخاطرة المقبولة؟', 'options' => ['50%', '1%'], 'answer' => 1]];

        $this->actingAs($member)->postJson('/api/admin/skill-paths', ['title' => 'x'])->assertForbidden();
        $path = $this->actingAs($admin)->postJson('/api/admin/skill-paths', ['title' => 'التداول'])->assertCreated()->json('paths.0');
        $this->postJson("/api/admin/skill-paths/{$path['id']}/levels", ['title' => 'x', 'quiz' => [['question' => 'q', 'options' => ['a', 'b'], 'answer' => 5]]])->assertUnprocessable();
        $this->postJson("/api/admin/skill-paths/{$path['id']}/levels", ['title' => 'الأساسيات', 'quiz' => $quiz])->assertOk()->assertJsonPath('paths.0.levels.0.quiz.0.answer', 0);
        $this->postJson("/api/admin/skill-paths/{$path['id']}/levels", ['title' => 'التطبيق', 'challenge' => 'افتح حساباً تجريبياً'])->assertOk();
        [$first, $second] = SkillLevel::orderBy('position')->get()->all();

        $levels = $this->actingAs($member)->getJson('/api/skills')->assertOk()->json('paths.0.levels');
        $this->assertArrayNotHasKey('answer', $levels[0]['quiz'][0]);
        $this->assertSame([true, false], array_column($levels, 'unlocked'));

        $this->postJson("/api/skill-levels/{$second->id}/attempt")->assertUnprocessable();
        $this->postJson("/api/skill-levels/{$first->id}/attempt", ['answers' => [1, 1]])->assertOk()->assertJsonPath('passed', false)->assertJsonPath('score', 50);
        $this->postJson("/api/skill-levels/{$first->id}/attempt", ['answers' => [0, 1]])->assertOk()->assertJsonPath('passed', true)->assertJsonPath('paths.0.levels.1.unlocked', true);
        // A level without a quiz is passed by confirming its challenge.
        $this->postJson("/api/skill-levels/{$second->id}/attempt")->assertOk()->assertJsonPath('passed', true);
        $this->getJson('/api/timeline')->assertJsonFragment(['key' => "skill:{$first->id}"]);

        // A hidden path cannot be attempted.
        SkillPath::query()->update(['is_active' => false]);
        $this->getJson('/api/skills')->assertJsonCount(0, 'paths');
        $this->postJson("/api/skill-levels/{$first->id}/attempt", ['answers' => [0, 1]])->assertNotFound();
    }

    public function test_a_portfolio_is_shared_only_as_far_as_its_owner_allows(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['display_name' => 'نورا']);
        $member = User::factory()->create();

        $this->actingAs($owner)->postJson('/api/portfolio', ['title' => 'x', 'link' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('link');
        $project = $this->post('/api/portfolio', ['title' => 'خطة تداول', 'description' => 'خطة كاملة', 'category' => 'trading', 'skills' => ['تحليل فني'], 'link' => 'https://example.com/plan', 'image' => UploadedFile::fake()->image('p.png')])
            ->assertCreated()->assertJsonPath('visibility', 'private')->assertJsonPath('slug', null)->json('projects.0');
        $this->assertStringStartsWith('/media/portfolio/', $project['imageUrl']);
        $this->actingAs($member)->postJson("/api/portfolio/{$project['id']}", ['title' => 'x'])->assertNotFound();
        $this->actingAs($member)->deleteJson("/api/portfolio/{$project['id']}")->assertNotFound();

        // Members only: signed-in members can open the link, visitors cannot.
        $slug = $this->actingAs($owner)->putJson('/api/portfolio-settings', ['visibility' => 'members'])->assertOk()->json('slug');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}$/', $slug);
        $this->actingAs($member)->getJson("/api/public/portfolio/{$slug}")->assertOk()->assertJsonPath('owner.displayName', 'نورا')->assertJsonPath('projects.0.title', 'خطة تداول')->assertJsonMissingPath('owner.email');

        $this->actingAs($owner)->putJson('/api/portfolio-settings', ['visibility' => 'public'])->assertOk();

        // A new link retires the old one; private closes it to everyone but the owner.
        $new = $this->actingAs($owner)->putJson('/api/portfolio-settings', ['newLink' => true])->json('slug');
        $this->assertNotSame($slug, $new);
        $this->getJson("/api/public/portfolio/{$slug}")->assertNotFound();
        $this->putJson('/api/portfolio-settings', ['visibility' => 'private'])->assertOk();
        $this->getJson("/api/public/portfolio/{$new}")->assertOk();
        $this->actingAs($member)->getJson("/api/public/portfolio/{$new}")->assertNotFound();
    }

    public function test_visitors_without_an_account_open_only_public_portfolios(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['portfolio_slug' => str_repeat('a', 20), 'portfolio_visibility' => 'members'])->save();
        $owner->portfolioProjects()->create(['title' => 'مشروع']);

        $this->getJson('/api/public/portfolio/'.str_repeat('a', 20))->assertNotFound();
        $owner->forceFill(['portfolio_visibility' => 'public'])->save();
        $this->getJson('/api/public/portfolio/'.str_repeat('a', 20))->assertOk()->assertJsonCount(1, 'projects');
        $owner->forceFill(['portfolio_visibility' => 'private'])->save();
        $this->getJson('/api/public/portfolio/'.str_repeat('a', 20))->assertNotFound();
    }

    public function test_the_export_contains_only_the_members_own_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Task::factory()->for($user)->create(['title' => 'مهمتي']);
        Task::factory()->for($other)->create(['title' => 'مهمة غيري']);

        $response = $this->actingAs($user)->get('/api/profile/export')->assertOk()->assertHeader('content-type', 'application/json');
        $data = json_decode($response->streamedContent(), true);
        $this->assertSame(['مهمتي'], array_column($data['tasks'], 'title'));
        $this->assertSame($user->email, $data['profile']['email']);
        $this->assertArrayNotHasKey('password', $data['profile']);
        $this->getJson('/api/profile/backup-status')->assertOk()->assertJsonPath('lastBackupAt', null);
    }
}
