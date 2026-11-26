<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\Book;
use App\Models\Lesson;
use App\Models\StoreItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionAndBooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_opens_chosen_services_for_a_member_until_a_date(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-11-26 10:00:00');
        Carbon::setTestNow('2026-11-26 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$member, $other] = [User::factory()->create(), User::factory()->create()];

        $book = $this->actingAs($admin)->post('/api/admin/books', ['title' => 'أسرار التداول', 'author' => 'صبحي', 'description' => 'كتاب', 'file' => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'), 'cover' => UploadedFile::fake()->image('c.jpg')])
            ->assertCreated()->assertJsonPath('books.0.hasFile', true)->json('books.0');
        $second = $this->post('/api/admin/books', ['title' => 'كتاب آخر', 'file' => UploadedFile::fake()->create('b2.pdf', 50, 'application/pdf')])->assertCreated()->json('books.1');
        $lesson = Lesson::create(['title' => 'درس مدفوع', 'body' => 'سري', 'is_premium' => true]);
        $item = StoreItem::create(['name' => 'لقب VIP', 'kind' => 'title', 'value' => 'VIP', 'price' => 0, 'is_exclusive' => true]);

        // Before anything is opened: the shelf is visible, the books are sealed.
        $this->actingAs($member)->getJson('/api/books')->assertOk()->assertJsonCount(2, 'books')->assertJsonPath('books.0.unlocked', false)->assertJsonPath('books.0.fileName', null);
        $this->get("/api/books/{$book['id']}/read")->assertNotFound();
        $this->actingAs($member)->getJson('/api/store')->assertJsonPath('items.0.allowed', false);
        $this->postJson("/api/store/{$item->id}/buy")->assertUnprocessable();
        $this->getJson("/api/admin/users/{$member->id}/subscription")->assertForbidden();

        $this->actingAs($admin)->getJson("/api/admin/users/{$member->id}/subscription")->assertOk()->assertJsonPath('active', false)->assertJsonCount(2, 'options.book')->assertJsonCount(1, 'options.store_item');
        $this->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => true, 'premiumUntil' => '2026-11-20', 'grants' => []])->assertUnprocessable()->assertJsonValidationErrors('premiumUntil');
        $this->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => true, 'premiumUntil' => '2026-12-31', 'grants' => ['book' => [$book['id']], 'lesson' => [$lesson->id], 'store_item' => [$item->id]]])
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('premiumUntil', '2026-12-31')->assertJsonPath('grants.book', [$book['id']]);

        // Only what was chosen opens, and only for this member.
        $this->actingAs($member->refresh())->getJson('/api/books')->assertJsonPath('books.0.unlocked', true)->assertJsonPath('books.1.unlocked', false);
        $this->get("/api/books/{$book['id']}/read")->assertOk();
        $this->get("/api/books/{$second['id']}/read")->assertNotFound();
        $this->getJson('/api/lessons')->assertJsonPath('lessons.0.locked', false);
        $this->postJson("/api/store/{$item->id}/buy")->assertOk();
        $this->getJson('/api/auth/me')->assertJsonPath('user.isPremium', true);
        $this->actingAs($other)->get("/api/books/{$book['id']}/read")->assertNotFound();

        // Past the last day, everything closes again without anyone touching it.
        CarbonImmutable::setTestNow('2027-01-01 10:00:00');
        Carbon::setTestNow('2027-01-01 10:00:00');
        $this->actingAs($member->refresh())->get("/api/books/{$book['id']}/read")->assertNotFound();
        $this->getJson('/api/auth/me')->assertJsonPath('user.isPremium', false);

        // Renewed with no end date, then switched off: switching off closes it at once.
        $this->actingAs($admin)->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => true, 'premiumUntil' => null, 'grants' => ['book' => [$book['id'], $second['id']]]])->assertOk()->assertJsonPath('premiumUntil', null);
        $this->actingAs($member->refresh())->get("/api/books/{$second['id']}/read")->assertOk();
        $this->actingAs($admin)->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => false, 'premiumUntil' => null, 'grants' => ['book' => [$book['id']]]])->assertOk()->assertJsonPath('active', false);
        $this->actingAs($member->refresh())->get("/api/books/{$book['id']}/read")->assertNotFound();

        // Deleting a book removes its file and its grants.
        $this->actingAs($admin)->deleteJson("/api/admin/books/{$book['id']}")->assertNoContent();
        $this->assertSame(0, $member->subscriptionGrants()->where('grantable_type', 'book')->where('grantable_id', $book['id'])->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('book-files'));
        $this->assertSame(['premium_granted', 'premium_granted', 'premium_revoked'], array_values(array_intersect(AdminAudit::orderBy('id')->pluck('action')->all(), ['premium_granted', 'premium_revoked', 'subscription_updated'])));
    }

    public function test_an_admin_names_the_membership_and_it_shows_only_while_active(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $this->actingAs($admin)->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => true, 'premiumUntil' => null, 'tier' => 'gold', 'grants' => []])
            ->assertOk()->assertJsonPath('tier', 'gold');
        $this->actingAs($member->refresh())->getJson('/api/auth/me')->assertJsonPath('user.premiumTier', 'gold');

        $this->actingAs($admin)->putJson("/api/admin/users/{$member->id}/subscription", ['isPremium' => false, 'premiumUntil' => null, 'tier' => 'gold', 'grants' => []])->assertOk();
        $this->actingAs($member->refresh())->getJson('/api/auth/me')->assertJsonPath('user.premiumTier', null);
    }

    public function test_the_subscription_note_appears_above_every_private_conversation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $this->actingAs($member)->getJson('/api/messages')->assertJsonPath('notice', null);
        $this->putJson('/api/admin/subscription-notice', ['notice' => 'x'])->assertForbidden();

        $this->actingAs($admin)->putJson('/api/admin/subscription-notice', ['notice' => 'للاشتراك حوّل على الحساب وابعت الإثبات'])->assertOk();
        $this->actingAs($member)->getJson('/api/messages')->assertJsonPath('notice', 'للاشتراك حوّل على الحساب وابعت الإثبات')->assertJsonCount(0, 'messages');
        $this->actingAs($admin)->putJson('/api/admin/subscription-notice', ['notice' => ''])->assertOk();
        $this->actingAs($member)->getJson('/api/messages')->assertJsonPath('notice', null);
    }
}
