<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PremiumAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_only_lessons_are_listed_but_sealed_until_access_is_granted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        Lesson::create(['title' => 'مجاني', 'body' => 'نص مفتوح', 'image_path' => 'lessons/free.jpg']);
        Lesson::create(['title' => 'للمشتركين', 'body' => 'نص سري', 'image_path' => 'lessons/paid.jpg', 'is_premium' => true]);

        $this->actingAs($member)->getJson('/api/lessons')->assertOk()
            ->assertJsonPath('lessons.0.locked', false)->assertJsonPath('lessons.0.body', 'نص مفتوح')
            ->assertJsonPath('lessons.1.title', 'للمشتركين')->assertJsonPath('lessons.1.locked', true)
            ->assertJsonPath('lessons.1.body', '')->assertJsonPath('lessons.1.imageUrl', null);
        // Administrators always read everything.
        $this->actingAs($admin)->getJson('/api/lessons')->assertJsonPath('lessons.1.locked', false)->assertJsonPath('lessons.1.body', 'نص سري');

        // A member cannot grant themselves access, at sign-up or through their profile.
        $this->actingAs($member)->putJson('/api/profile', [
            'displayName' => 'x', 'surname' => '', 'bio' => '', 'timezone' => 'UTC', 'language' => 'ar', 'weeklyGoal' => 5, 'dailyFocusMinutes' => 30, 'avatarUrl' => null, 'isPremium' => true,
        ])->assertOk()->assertJsonPath('user.isPremium', false);
        $this->actingAs($member)->patchJson("/api/admin/users/{$member->id}", ['isPremium' => true])->assertForbidden();

        $this->actingAs($admin)->patchJson("/api/admin/users/{$member->id}", ['isPremium' => true])->assertOk()->assertJsonPath('user.isPremium', true);
        $this->actingAs($member->refresh())->getJson('/api/lessons')->assertJsonPath('lessons.1.locked', false)->assertJsonPath('lessons.1.body', 'نص سري')
            ->assertJsonPath('lessons.1.imageUrl', '/media/lessons/paid.jpg');

        $this->actingAs($admin)->patchJson("/api/admin/users/{$member->id}", ['isPremium' => false])->assertOk();
        $this->actingAs($member->refresh())->getJson('/api/lessons')->assertJsonPath('lessons.1.locked', true);
        $this->assertSame(['premium_granted', 'premium_revoked'], AdminAudit::orderBy('id')->pluck('action')->all());
    }

    public function test_admin_marks_a_lesson_as_subscriber_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $id = $this->actingAs($admin)->post('/api/admin/lessons', ['title' => 't', 'body' => 'b', 'isPremium' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('lesson.isPremium', true)->json('lesson.id');
        $this->actingAs($admin)->post("/api/admin/lessons/{$id}", ['isPremium' => '0'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('lesson.isPremium', false);
    }

    public function test_profile_keeps_a_nickname(): void
    {
        $user = User::factory()->create();
        $payload = ['displayName' => 'صبحي', 'surname' => 'أبو عيشة', 'nickname' => 'أبو محمد', 'bio' => '', 'timezone' => 'Asia/Gaza', 'language' => 'ar', 'weeklyGoal' => 20, 'dailyFocusMinutes' => 120, 'avatarUrl' => null];

        $this->actingAs($user)->putJson('/api/profile', $payload)->assertOk()->assertJsonPath('user.nickname', 'أبو محمد')->assertJsonPath('user.surname', 'أبو عيشة');
        $this->actingAs($user)->putJson('/api/profile', [...$payload, 'nickname' => null])->assertOk()->assertJsonPath('user.nickname', '');
    }

    public function test_password_change_needs_the_current_password_and_signs_out_other_devices(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);
        $otherUser = User::factory()->create();
        $row = fn (string $id, User $owner) => ['id' => $id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()];
        DB::table('sessions')->insert([$row('other-device', $user), $row('someone-else', $otherUser)]);

        $this->actingAs($user)->putJson('/api/profile/password', ['currentPassword' => 'wrong', 'password' => 'new-password-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('currentPassword');
        $this->actingAs($user)->putJson('/api/profile/password', ['currentPassword' => 'old-password-1', 'password' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('old-password-1', $user->refresh()->password));

        $this->actingAs($user)->putJson('/api/profile/password', ['currentPassword' => 'old-password-1', 'password' => 'new-password-1'])->assertNoContent();
        $this->assertTrue(Hash::check('new-password-1', $user->refresh()->password));
        $this->assertSame(['someone-else'], DB::table('sessions')->pluck('id')->all(), 'only this account\'s other sessions are ended');

        DB::table('sessions')->insert($row('another-device', $user));
        $this->actingAs($user)->postJson('/api/profile/sign-out-others')->assertNoContent();
        $this->assertSame(['someone-else'], DB::table('sessions')->pluck('id')->all());
        $this->postJson('/api/profile/sign-out-others')->assertNoContent(); // still signed in on this device
    }
}
