<?php

namespace Tests\Feature;

use App\Models\StoreItem;
use App\Models\StorePurchase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortraitsAndAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function avatars(User $user): array
    {
        return collect($this->actingAs($user)->getJson('/api/store')->assertOk()->json('items'))->where('kind', 'avatar')->pluck('id')->all();
    }

    public function test_profile_pictures_follow_the_members_gender(): void
    {
        StoreItem::where('kind', 'avatar')->delete();
        $men = StoreItem::create(['name' => 'm', 'kind' => 'avatar', 'price' => 1, 'audience' => 'male']);
        $women = StoreItem::create(['name' => 'f', 'kind' => 'avatar', 'price' => 1, 'audience' => 'female']);
        $all = StoreItem::create(['name' => 'a', 'kind' => 'avatar', 'price' => 1]);

        $this->assertEqualsCanonicalizing([$men->id, $all->id], $this->avatars(User::factory()->create(['gender' => 'male'])));
        $this->assertEqualsCanonicalizing([$women->id, $all->id], $this->avatars(User::factory()->create(['gender' => 'female'])));
        $this->assertEqualsCanonicalizing([$men->id, $women->id, $all->id], $this->avatars(User::factory()->create(['gender' => null])));

        // A picture already bought stays in the store's "owned" list even after a change of gender.
        $switched = User::factory()->create(['gender' => 'female']);
        StorePurchase::create(['user_id' => $switched->id, 'store_item_id' => $men->id, 'price' => 1]);
        $this->assertContains($men->id, $this->avatars($switched));
    }

    public function test_built_in_portraits_can_be_worn(): void
    {
        $portrait = StoreItem::where('image_path', 'builtin:avatars/gold.webp')->first()
            ?? tap(new StoreItem(['name' => 'gold', 'kind' => 'avatar', 'price' => 1, 'audience' => 'male']), function (StoreItem $item): void {
                $item->image_path = 'builtin:avatars/gold.webp';
                $item->save();
            });
        $member = User::factory()->create();
        StorePurchase::create(['user_id' => $member->id, 'store_item_id' => $portrait->id, 'price' => 0]);
        $this->actingAs($member)->putJson('/api/store/equip', ['kind' => 'avatar', 'itemId' => $portrait->id])->assertOk();
        $this->assertSame('/images/avatars/gold.webp', $member->fresh()->avatar_url);
        // Saving the profile afterwards keeps that picture.
        $this->putJson('/api/profile', ['displayName' => 'x', 'surname' => '', 'bio' => '', 'timezone' => 'UTC', 'language' => 'ar', 'weeklyGoal' => 5, 'dailyFocusMinutes' => 30, 'avatarUrl' => '/images/avatars/gold.webp'])->assertOk();
    }

    public function test_admins_delete_member_accounts_and_the_email_is_free_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['email' => 'tester@example.com']);
        $member->tasks()->create(['title' => 't', 'period_type' => 'daily', 'period_start' => now()->toDateString(), 'period_end' => now()->toDateString()]);
        $staff = User::factory()->create(['role' => 'moderator', 'permissions' => ['users']]);

        $this->actingAs($admin)->deleteJson("/api/admin/users/{$staff->id}")->assertUnprocessable();
        $this->deleteJson("/api/admin/users/{$member->id}")->assertOk();
        $this->assertNull(User::find($member->id));
        $this->assertSame(0, Task::where('user_id', $member->id)->count());

        // The address can sign up again.
        $this->assertNotNull(User::factory()->create(['email' => 'tester@example.com'])->id);
    }

    public function test_only_a_full_admin_can_clear_every_member_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $moderator = User::factory()->create(['role' => 'moderator', 'permissions' => ['users']]);
        User::factory()->count(3)->create();

        $this->actingAs($moderator)->postJson('/api/admin/members/purge', ['confirm' => 'DELETE'])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/members/purge', ['confirm' => 'nope'])->assertUnprocessable();
        $this->postJson('/api/admin/members/purge', ['confirm' => 'DELETE'])->assertOk()->assertJsonPath('deleted', 3);
        $this->assertSame(2, User::count());
    }
}
