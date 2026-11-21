<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Motivation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_payload_matches_the_frontend_contract(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->completed()->create();
        Goal::factory()->for($user)->create();
        $user->achievements()->create(['key' => 'first_task', 'earned_at' => now()]);
        $user->focusSessions()->createMany([
            ['minutes' => 25, 'day' => '2026-11-21'],
            ['minutes' => 50, 'day' => '2026-11-20'],
        ]);
        Motivation::create(['title' => 't', 'body' => 'b', 'category' => 'growth']);
        Task::factory()->create(); // someone else's

        $response = $this->actingAs($user)->getJson('/api/dashboard?date=2026-11-21')->assertOk();

        $response->assertJsonStructure([
            'user' => ['id', 'email', 'displayName', 'surname', 'avatarUrl', 'role', 'bio', 'timezone', 'language', 'weeklyGoal', 'dailyFocusMinutes', 'createdAt'],
            'tasks' => [['id', 'title', 'description', 'periodType', 'periodStart', 'periodEnd', 'dueDate', 'priority', 'status', 'categoryId', 'completedAt', 'createdAt']],
            'categories' => [['id', 'name', 'color']],
            'goals' => [['id', 'title', 'description', 'periodType', 'periodStart', 'periodEnd', 'targetValue', 'currentValue', 'status']],
            'achievements' => [['id', 'key', 'earnedAt']],
            'motivations' => [['id', 'title', 'body', 'category', 'isActive', 'createdAt']],
            'focus' => ['totalMinutes', 'todayMinutes'],
            'review',
        ]);
        $response->assertJsonCount(1, 'tasks')->assertJsonCount(4, 'categories')
            ->assertJsonPath('focus', ['totalMinutes' => 75, 'todayMinutes' => 25])
            ->assertJsonPath('review', null);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response->json('tasks.0.periodStart'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $response->json('tasks.0.completedAt'));

        $this->getJson('/api/dashboard?date=21-11-2026')->assertStatus(422);
    }

    public function test_daily_review_is_upserted_per_day_and_private(): void
    {
        $user = User::factory()->create();
        $body = ['date' => '2026-11-21', 'win' => 'أغلقت صفقة', 'unfinished' => '', 'lesson' => 'الصبر', 'tomorrow' => null];

        $this->actingAs($user)->putJson('/api/reviews/2026-11-21', $body)
            ->assertOk()
            ->assertJson(['review' => ['date' => '2026-11-21', 'win' => 'أغلقت صفقة', 'unfinished' => '', 'lesson' => 'الصبر', 'tomorrow' => '']]);

        $this->putJson('/api/reviews/2026-11-21', ['win' => 'محدّث'] + $body)->assertOk()->assertJsonPath('review.win', 'محدّث');
        $this->assertSame(1, $user->dailyReviews()->count());

        $this->getJson('/api/dashboard?date=2026-11-21')->assertJsonPath('review.win', 'محدّث');
        $this->assertNotNull($this->getJson('/api/dashboard?date=2026-11-21')->json('review.savedAt'));
        $this->getJson('/api/dashboard?date=2026-11-22')->assertJsonPath('review', null);

        $this->putJson('/api/reviews/2026-13-45', $body)->assertStatus(422);
        $this->actingAs(User::factory()->create())->getJson('/api/dashboard?date=2026-11-21')->assertJsonPath('review', null);
    }

    public function test_profile_update_cannot_change_role_or_email_and_validates_avatar(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $payload = [
            'displayName' => 'Sobhi', 'surname' => 'Abo Aisha', 'bio' => 'trader', 'timezone' => 'Asia/Gaza', 'language' => 'en',
            'weeklyGoal' => 30, 'dailyFocusMinutes' => 90, 'avatarUrl' => 'data:image/jpeg;base64,/9j/4AAQSkZJRg==',
            'role' => 'admin', 'email' => 'other@example.com',
        ];

        $this->actingAs($user)->putJson('/api/profile', $payload)
            ->assertOk()
            ->assertJson(['user' => [
                'displayName' => 'Sobhi', 'surname' => 'Abo Aisha', 'bio' => 'trader', 'language' => 'en', 'weeklyGoal' => 30,
                'dailyFocusMinutes' => 90, 'avatarUrl' => 'data:image/jpeg;base64,/9j/4AAQSkZJRg==', 'role' => 'user', 'email' => 'me@example.com',
            ]]);

        $this->putJson('/api/profile', ['avatarUrl' => 'https://evil.example/x.png', 'timezone' => 'Mars/Olympus', 'weeklyGoal' => 0] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors(['avatarUrl', 'timezone', 'weeklyGoal']);

        $this->putJson('/api/profile', ['avatarUrl' => null, 'surname' => null, 'bio' => null] + $payload)
            ->assertOk()->assertJsonPath('user.avatarUrl', null)->assertJsonPath('user.surname', '')->assertJsonPath('user.bio', '');
    }

    public function test_spa_shell_is_served_for_application_urls_only(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('id="app"', false);
        $this->get('/app/tasks/daily')->assertOk()->assertSee('id="app"', false);
        $this->get('/reset-password/some-token?email=a@b.c')->assertOk();
        $this->getJson('/api/nope')->assertNotFound();
    }
}
