<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Services\Assistant\AssistantClient;
use App\Services\Assistant\AssistantReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantAndWorldTest extends TestCase
{
    use RefreshDatabase;

    /** A stand-in for Claude that records what it was asked. */
    private function fakeClient(string $text = 'ابدأ بأصعب مهمة.', bool $refused = false): object
    {
        $fake = new class($text, $refused) implements AssistantClient
        {
            public array $calls = [];

            public function __construct(private string $text, private bool $refused) {}

            public function reply(array $system, array $messages): AssistantReply
            {
                $this->calls[] = compact('system', 'messages');

                return new AssistantReply($this->text, $this->refused, 1200, 80);
            }
        };
        $this->app->instance(AssistantClient::class, $fake);

        return $fake;
    }

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_premium' => true])->save();
        $user->subscriptionGrants()->create(['grantable_type' => 'assistant', 'grantable_id' => 0]);

        return $user->refresh();
    }

    public function test_the_ai_assistant_answers_subscribers_it_was_opened_for_within_a_daily_limit(): void
    {
        config(['assistant.api_key' => 'test-key', 'assistant.daily_limit' => 2]);
        $fake = $this->fakeClient();
        $member = User::factory()->create();
        $ask = ['messages' => [['role' => 'user', 'content' => 'شو أعمل اليوم؟']]];

        $this->actingAs($member)->getJson('/api/assistant')->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('configured', true);
        $this->postJson('/api/assistant', $ask)->assertForbidden();

        $subscriber = $this->subscriber();
        Task::factory()->for($subscriber)->create(['title' => 'مراجعة الصفقات', 'period_type' => 'daily', 'period_start' => now()->toDateString(), 'period_end' => now()->toDateString()]);
        $this->actingAs($subscriber)->postJson('/api/assistant', $ask)->assertOk()->assertJsonPath('reply', 'ابدأ بأصعب مهمة.')->assertJsonPath('remaining', 1);
        // The member's situation travels in the second system block, after the cached instructions.
        $this->assertSame(['type' => 'ephemeral'], $fake->calls[0]['system'][0]['cacheControl']);
        $this->assertStringContainsString('مراجعة الصفقات', $fake->calls[0]['system'][1]['text']);

        $this->postJson('/api/assistant', ['messages' => [['role' => 'user', 'content' => 'x'], ['role' => 'assistant', 'content' => 'y']]])->assertUnprocessable();
        $this->postJson('/api/assistant', $ask)->assertOk()->assertJsonPath('remaining', 0);
        $this->postJson('/api/assistant', $ask)->assertStatus(429);
        $this->assertCount(2, $fake->calls);
    }

    public function test_it_stays_off_without_an_api_key_and_softens_refusals(): void
    {
        config(['assistant.api_key' => null]);
        $this->fakeClient();
        $subscriber = $this->subscriber();
        $this->actingAs($subscriber)->getJson('/api/assistant')->assertJsonPath('configured', false)->assertJsonPath('allowed', true);
        $this->postJson('/api/assistant', ['messages' => [['role' => 'user', 'content' => 'x']]])->assertStatus(503);

        config(['assistant.api_key' => 'test-key']);
        $this->fakeClient('', refused: true);
        $reply = $this->postJson('/api/assistant', ['messages' => [['role' => 'user', 'content' => 'x']]])->assertOk()->json('reply');
        $this->assertNotSame('', $reply);
    }

    public function test_an_admin_opens_the_assistant_in_a_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $this->actingAs($admin)->getJson("/api/admin/users/{$member->id}/subscription")->assertJsonCount(1, 'options.assistant');
        $this->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => true, 'premiumUntil' => null, 'grants' => ['assistant' => [0]]])->assertOk()->assertJsonPath('grants.assistant', [0]);
        $this->assertTrue($member->refresh()->hasGrant('assistant', 0));
    }

    public function test_the_world_is_built_from_the_members_own_numbers(): void
    {
        $member = User::factory()->create();
        Task::factory()->count(3)->for($member)->create(['status' => 'completed', 'completed_at' => now(), 'category_id' => $member->categories()->value('id')]);
        Task::factory()->for(User::factory()->create())->create(['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($member)->getJson('/api/world')->assertOk()
            ->assertJsonPath('tasksCompleted', 3)->assertJsonPath('areas.0.done', 3)->assertJsonCount(4, 'areas')
            ->assertJsonPath('theme', null)->assertJsonStructure(['name', 'goalsCompleted', 'habitChecks', 'streak', 'pointsEarned', 'skillLevels', 'title']);
    }
}
