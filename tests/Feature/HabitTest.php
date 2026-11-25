<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HabitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tracker_requires_sign_in(): void
    {
        $this->getJson('/api/habits?month=2026-11')->assertUnauthorized();
        $this->postJson('/api/habits', ['name' => 'x'])->assertUnauthorized();
    }

    public function test_member_tracks_habits_for_a_month(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gym = $this->postJson('/api/habits', ['name' => 'الرياضة', 'targetDays' => 20])->assertCreated()->json('habit');
        $read = $this->postJson('/api/habits', ['name' => 'القراءة'])->assertCreated()->json('habit');
        $this->assertNull($read['targetDays']);
        $this->assertGreaterThan($gym['position'], $read['position']);

        $this->putJson("/api/habits/{$gym['id']}/checks/2026-11-03", ['done' => true])->assertNoContent();
        // Checking twice keeps a single row; unchecking removes it.
        $this->putJson("/api/habits/{$gym['id']}/checks/2026-11-03", ['done' => true])->assertNoContent();
        $this->putJson("/api/habits/{$read['id']}/checks/2026-11-03", ['done' => true])->assertNoContent();
        $this->putJson("/api/habits/{$read['id']}/checks/2026-11-03", ['done' => false])->assertNoContent();
        $this->putJson("/api/habits/{$gym['id']}/checks/2026-10-31", ['done' => true])->assertNoContent();
        $this->assertSame(2, HabitCheck::count());

        $this->putJson('/api/moods/2026-11-03', ['mood' => 8, 'motivation' => null])->assertOk();
        $this->putJson('/api/moods/2026-11-03', ['mood' => 8, 'motivation' => 6])->assertOk();
        $this->putJson('/api/moods/2026-11-04', ['mood' => 11, 'motivation' => 5])->assertUnprocessable();
        $this->putJson('/api/habit-notes/2026-11', ['body' => 'شهر جيد'])->assertOk()->assertJsonPath('note', 'شهر جيد');

        // Only November's data comes back for November.
        $this->getJson('/api/habits?month=2026-11')->assertOk()
            ->assertJsonCount(2, 'habits')
            ->assertExactJson([
                'days' => [],
                'habits' => [
                    ['id' => $gym['id'], 'name' => 'الرياضة', 'targetDays' => 20, 'position' => $gym['position']],
                    ['id' => $read['id'], 'name' => 'القراءة', 'targetDays' => null, 'position' => $read['position']],
                ],
                'checks' => [['habitId' => $gym['id'], 'day' => '2026-11-03']],
                'moods' => [['day' => '2026-11-03', 'mood' => 8, 'motivation' => 6]],
                'note' => 'شهر جيد',
            ]);
        $this->getJson('/api/habits?month=2026-10')->assertJsonCount(1, 'checks')->assertJsonCount(0, 'moods')->assertJsonPath('note', '');

        // Clearing both ratings removes the entry; an empty note removes the note.
        $this->putJson('/api/moods/2026-11-03', ['mood' => null, 'motivation' => null])->assertOk();
        $this->putJson('/api/habit-notes/2026-11', ['body' => '  '])->assertOk()->assertJsonPath('note', '');
        $this->getJson('/api/habits?month=2026-11')->assertJsonCount(0, 'moods')->assertJsonPath('note', '');

        $this->patchJson("/api/habits/{$gym['id']}", ['name' => 'النادي', 'targetDays' => null])->assertOk()->assertJsonPath('habit.targetDays', null);
        $this->deleteJson("/api/habits/{$gym['id']}")->assertNoContent();
        $this->assertSame(0, HabitCheck::count(), 'deleting a habit removes its checks');
    }

    public function test_a_day_cannot_be_recorded_before_it_happens(): void
    {
        $user = User::factory()->create();
        $habit = $user->habits()->create(['name' => 'ماء']);
        $future = now()->addDays(3)->toDateString();

        $this->actingAs($user)->putJson("/api/habits/{$habit->id}/checks/{$future}", ['done' => true])->assertUnprocessable()->assertJsonValidationErrors('day');
        $this->actingAs($user)->putJson("/api/moods/{$future}", ['mood' => 5, 'motivation' => 5])->assertUnprocessable();
        $this->actingAs($user)->putJson("/api/habits/{$habit->id}/checks/".now()->toDateString(), ['done' => true])->assertNoContent();
    }

    public function test_members_cannot_touch_each_others_habits(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $habit = $owner->habits()->create(['name' => 'خاص']);
        $habit->checks()->create(['day' => '2026-11-02']);

        $this->actingAs($other)->getJson('/api/habits?month=2026-11')->assertJsonCount(0, 'habits')->assertJsonCount(0, 'checks');
        $this->actingAs($other)->patchJson("/api/habits/{$habit->id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/habits/{$habit->id}")->assertForbidden();
        $this->actingAs($other)->putJson("/api/habits/{$habit->id}/checks/2026-11-02", ['done' => false])->assertForbidden();
        $this->assertSame(1, HabitCheck::count());
    }

    public function test_the_number_of_habits_is_capped(): void
    {
        $user = User::factory()->create();
        foreach (range(1, Habit::MAX_PER_USER) as $i) {
            $user->habits()->create(['name' => "h{$i}"]);
        }

        $this->actingAs($user)->postJson('/api/habits', ['name' => 'one more'])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_a_day_holds_a_picture_and_a_note(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['timezone' => 'UTC']);
        $day = now()->subDay()->toDateString();
        $month = substr($day, 0, 7);

        $entry = $this->actingAs($user)->post("/api/habit-days/{$day}", ['note' => 'يوم ممتاز', 'image' => UploadedFile::fake()->image('d.jpg')])->assertOk()->json('entry');
        $this->assertSame('يوم ممتاز', $entry['note']);
        $this->assertStringStartsWith('/media/days/', $entry['imageUrl']);
        $this->getJson("/api/habits?month={$month}")->assertJsonCount(1, 'days')->assertJsonPath('days.0.day', $day);
        // Nobody else sees it, and tomorrow cannot be written yet.
        $this->actingAs(User::factory()->create())->getJson("/api/habits?month={$month}")->assertJsonCount(0, 'days');
        $this->actingAs($user)->postJson('/api/habit-days/'.now()->addDays(2)->toDateString(), ['note' => 'x'])->assertUnprocessable();

        // Changing the note keeps the picture; removing both removes the entry and its file.
        $this->postJson("/api/habit-days/{$day}", ['note' => 'معدّلة'])->assertOk()->assertJsonPath('entry.imageUrl', $entry['imageUrl']);
        $this->postJson("/api/habit-days/{$day}", ['note' => '', 'removeImage' => true])->assertOk()->assertJsonPath('entry', null);
        $this->assertSame(0, $user->dayEntries()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('days'));
    }
}
