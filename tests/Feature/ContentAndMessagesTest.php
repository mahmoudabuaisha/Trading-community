<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\Lesson;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentAndMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public static function adminOnlyRoutes(): array
    {
        return [
            ['GET', '/api/admin/settings'],
            ['PUT', '/api/admin/settings'],
            ['GET', '/api/admin/threads'],
            ['GET', '/api/admin/threads/{user}'],
            ['POST', '/api/admin/threads/{user}'],
            ['GET', '/api/admin/lessons'],
            ['POST', '/api/admin/lessons'],
            ['POST', '/api/admin/lessons/{lesson}'],
            ['DELETE', '/api/admin/lessons/{lesson}'],
        ];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_members_are_refused_on_the_new_admin_routes(string $method, string $uri): void
    {
        $member = User::factory()->create();
        $lesson = Lesson::create(['title' => 't', 'body' => 'b']);
        $uri = str_replace(['{user}', '{lesson}'], [$member->id, $lesson->id], $uri);
        $payload = ['body' => 'x', 'title' => 'x', 'taglineAr' => 'x', 'taglineEn' => null, 'welcomeSubAr' => null, 'welcomeSubEn' => null];

        $this->json($method, $uri, $payload)->assertUnauthorized();
        $this->actingAs($member)->json($method, $uri, $payload)->assertForbidden();
    }

    public function test_admin_overrides_dashboard_copy_and_clearing_restores_the_default(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($member)->getJson('/api/dashboard')->assertOk()->assertJsonPath('content.taglineAr', null);

        $this->actingAs($admin)->putJson('/api/admin/settings', [
            'taglineAr' => '  عبارة جديدة  ', 'taglineEn' => 'New line', 'welcomeSubAr' => 'ترحيب', 'welcomeSubEn' => '',
        ])->assertOk()->assertJsonPath('content.taglineAr', 'عبارة جديدة')->assertJsonPath('content.welcomeSubEn', null);

        $this->actingAs($member)->getJson('/api/dashboard')
            ->assertJsonPath('content.taglineAr', 'عبارة جديدة')->assertJsonPath('content.welcomeSubAr', 'ترحيب');
        $this->assertSame(1, AdminAudit::where('action', 'settings_updated')->count());

        $this->actingAs($admin)->putJson('/api/admin/settings', ['taglineAr' => null, 'taglineEn' => null, 'welcomeSubAr' => null, 'welcomeSubEn' => null])->assertOk();
        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonPath('content.taglineAr', null);

        $this->actingAs($admin)->putJson('/api/admin/settings', ['taglineAr' => str_repeat('ا', 161), 'taglineEn' => null, 'welcomeSubAr' => null, 'welcomeSubEn' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('taglineAr');
    }

    public function test_member_and_admin_exchange_private_messages_with_read_tracking(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($member)->postJson('/api/messages', ['body' => 'أحتاج مساعدة'])->assertCreated()->assertJsonPath('message.fromAdmin', false);
        $this->actingAs($member)->postJson('/api/messages', ['body' => ''])->assertUnprocessable();

        // The admin sees one thread with one unread message; another member sees nothing.
        $this->actingAs($admin)->getJson('/api/messages/unread')->assertJsonPath('admin', 1);
        $this->actingAs($other)->getJson('/api/messages')->assertOk()->assertJsonCount(0, 'messages');
        $this->actingAs($other)->getJson('/api/messages/unread')->assertExactJson(['member' => 0, 'admin' => 0]);
        $this->actingAs($admin)->getJson('/api/admin/threads')->assertOk()
            ->assertJsonCount(1, 'threads')->assertJsonPath('threads.0.user.id', $member->id)->assertJsonPath('threads.0.unread', 1);

        // Opening the thread marks the member's messages read.
        $this->actingAs($admin)->getJson("/api/admin/threads/{$member->id}")->assertOk()->assertJsonCount(1, 'messages');
        $this->actingAs($admin)->getJson('/api/messages/unread')->assertJsonPath('admin', 0);

        // The admin can also start a thread with a member who never wrote.
        $this->actingAs($admin)->postJson("/api/admin/threads/{$other->id}", ['body' => 'أهلاً بك'])->assertCreated()->assertJsonPath('message.fromAdmin', true);
        $this->actingAs($admin)->postJson("/api/admin/threads/{$member->id}", ['body' => 'تم'])->assertCreated();

        $this->actingAs($member)->getJson('/api/messages/unread')->assertJsonPath('member', 1);
        $this->actingAs($member)->getJson('/api/messages')->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.body', 'تم');
        $this->actingAs($member)->getJson('/api/messages/unread')->assertJsonPath('member', 0);
        // Reading as the member never touches the other member's unread state.
        $this->assertSame(1, Message::where('user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_admin_manages_lessons_with_images_and_members_see_published_ones_only(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $member = User::factory()->create();

        $created = $this->actingAs($admin)->post('/api/admin/lessons', [
            'title' => 'أساسيات الشموع', 'body' => 'شرح', 'isPublished' => '1', 'image' => UploadedFile::fake()->image('chart.png', 800, 600),
        ], ['Accept' => 'application/json'])->assertCreated();
        $lesson = Lesson::firstOrFail();
        Storage::disk('public')->assertExists($lesson->image_path);
        $this->assertSame('/media/'.$lesson->image_path, $created->json('lesson.imageUrl'));
        $this->get($created->json('lesson.imageUrl'))->assertOk();

        $this->actingAs($admin)->post('/api/admin/lessons', ['title' => 'مسودة', 'body' => 'نص', 'isPublished' => '0'], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($member)->getJson('/api/lessons')->assertOk()->assertJsonCount(1, 'lessons')->assertJsonPath('lessons.0.title', 'أساسيات الشموع');
        $this->actingAs($admin)->getJson('/api/admin/lessons')->assertJsonCount(2, 'lessons');

        // Replacing the image removes the old file; deleting the lesson removes the new one.
        $old = $lesson->image_path;
        $this->actingAs($admin)->post("/api/admin/lessons/{$lesson->id}", ['title' => 'معدّل', 'image' => UploadedFile::fake()->image('new.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('lesson.title', 'معدّل');
        Storage::disk('public')->assertMissing($old);
        $new = $lesson->refresh()->image_path;
        Storage::disk('public')->assertExists($new);

        $this->actingAs($admin)->post('/api/admin/lessons', ['title' => 'x', 'body' => 'y', 'image' => UploadedFile::fake()->create('doc.pdf', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->actingAs($admin)->deleteJson("/api/admin/lessons/{$lesson->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($new);
        $this->assertSame(['lesson_created', 'lesson_created', 'lesson_updated', 'lesson_deleted'], AdminAudit::orderBy('id')->pluck('action')->all());
    }
}
