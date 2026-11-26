<?php

namespace App\Http\Controllers;

use App\Http\Requests\HabitRequest;
use App\Http\Resources\HabitResource;
use App\Models\DayEntry;
use App\Models\Habit;
use App\Models\HabitCheck;
use App\Models\MoodEntry;
use App\Services\AchievementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HabitController extends Controller
{
    /** Everything the tracker shows for one month: habits, done-days, mood ratings and the month's note. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $user = $request->user();
        $start = CarbonImmutable::createFromFormat('!Y-m', $request->query('month'));
        $range = [$start->toDateString(), $start->endOfMonth()->toDateString()];

        $habits = $user->habits()->orderBy('position')->orderBy('id')->get();
        $checks = HabitCheck::whereIn('habit_id', $habits->pluck('id'))->whereBetween('day', $range)->get(['habit_id', 'day']);
        $moods = $user->moodEntries()->whereBetween('day', $range)->orderBy('day')->get();

        return response()->json([
            'habits' => HabitResource::collection($habits),
            'checks' => $checks->map(fn (HabitCheck $check): array => ['habitId' => $check->habit_id, 'day' => $check->day->toDateString()]),
            'moods' => $moods->map(fn (MoodEntry $entry): array => ['day' => $entry->day->toDateString(), 'mood' => $entry->mood, 'motivation' => $entry->motivation]),
            'days' => $user->dayEntries()->whereBetween('day', $range)->orderBy('day')->get()->map(fn (DayEntry $entry): array => $entry->toApi()),
            'note' => (string) $user->habitNotes()->where('month', $start->format('Y-m'))->value('body'),
        ]);
    }

    public function store(HabitRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user->habits()->count() >= Habit::MAX_PER_USER) {
            throw ValidationException::withMessages(['name' => __('validation.custom.habits.limit', ['max' => Habit::MAX_PER_USER])]);
        }
        $habit = $user->habits()->create([...$request->attributesForModel(), 'position' => (int) $user->habits()->max('position') + 1]);

        return response()->json(['habit' => new HabitResource($habit)], 201);
    }

    public function update(HabitRequest $request, Habit $habit): JsonResponse
    {
        Gate::authorize('update', $habit);
        $habit->update($request->attributesForModel());

        return response()->json(['habit' => new HabitResource($habit)]);
    }

    public function destroy(Habit $habit): Response
    {
        Gate::authorize('delete', $habit);
        $habit->delete();

        return response()->noContent();
    }

    /** Mark a habit done or not done on a day. Setting the same state twice is harmless. */
    /**
     * Pin a picture and a note to a day. Sent as multipart so the picture can
     * travel with the note; an entry left with neither is removed.
     */
    public function day(Request $request, string $day): JsonResponse
    {
        if ($request->has('removeImage')) {
            $request->merge(['removeImage' => filter_var($request->input('removeImage'), FILTER_VALIDATE_BOOLEAN)]);
        }
        $data = $request->validate([
            'note' => ['present', 'nullable', 'string', 'max:1000'],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'removeImage' => ['sometimes', 'boolean'],
        ]);
        // A day of the calendar can carry a note ahead of time; only real dates are accepted.
        abort_unless(checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4)), 422);

        $entry = $request->user()->dayEntries()->whereDate('day', $day)->first() ?? $request->user()->dayEntries()->make(['day' => $day]);
        $old = $entry->image_path;
        $entry->note = trim((string) $data['note']);
        if ($request->file('image')) {
            $entry->image_path = $request->file('image')->store(DayEntry::IMAGE_DIRECTORY, 'public');
        } elseif ($data['removeImage'] ?? false) {
            $entry->image_path = null;
        }
        if ($old && $old !== $entry->image_path) {
            Storage::disk('public')->delete($old);
        }
        if ($entry->note === '' && $entry->image_path === null) {
            $entry->exists && $entry->delete();

            return response()->json(['entry' => null]);
        }
        $entry->save();

        return response()->json(['entry' => $entry->toApi()]);
    }

    /** The pictures and notes pinned to the days of one month (for the calendar). */
    public function days(Request $request): JsonResponse
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $start = CarbonImmutable::createFromFormat('!Y-m', $request->query('month'));

        return response()->json(['days' => $request->user()->dayEntries()->whereBetween('day', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('day')->get()->map(fn (DayEntry $entry): array => $entry->toApi())]);
    }

    public function check(Request $request, Habit $habit, string $day): Response
    {
        Gate::authorize('update', $habit);
        $done = $request->validate(['done' => ['required', 'boolean']])['done'];
        $this->assertNotFuture($day);

        if ($done) {
            $habit->checks()->firstOrCreate(['day' => $day]);
            // Habit badges are awarded quietly here; the member sees them on the next refresh.
            app(AchievementService::class)->evaluate($request->user());
        } else {
            $habit->checks()->whereDate('day', $day)->delete();
        }

        return response()->noContent();
    }

    public function mood(Request $request, string $day): JsonResponse
    {
        $scale = ['present', 'nullable', 'integer', 'min:1', 'max:'.MoodEntry::SCALE_MAX];
        $data = $request->validate(['mood' => $scale, 'motivation' => $scale]);
        $this->assertNotFuture($day);
        $user = $request->user();

        if ($data['mood'] === null && $data['motivation'] === null) {
            $user->moodEntries()->whereDate('day', $day)->delete();
        } else {
            $entry = $user->moodEntries()->whereDate('day', $day)->first() ?? $user->moodEntries()->make(['day' => $day]);
            $entry->fill($data)->save();
        }

        return response()->json(['day' => $day, ...$data]);
    }

    public function note(Request $request, string $month): JsonResponse
    {
        $body = (string) ($request->validate(['body' => ['present', 'nullable', 'string', 'max:5000']])['body'] ?? '');
        $notes = $request->user()->habitNotes();

        if (trim($body) === '') {
            $notes->where('month', $month)->delete();
        } else {
            $note = $notes->where('month', $month)->first() ?? $request->user()->habitNotes()->make(['month' => $month]);
            $note->fill(['body' => $body])->save();
        }

        return response()->json(['note' => trim($body) === '' ? '' : $body]);
    }

    /** A day cannot be recorded before it happens; one day of slack covers time zones ahead of the server. */
    private function assertNotFuture(string $day): void
    {
        if ($day > now()->addDay()->toDateString()) {
            throw ValidationException::withMessages(['day' => __('validation.custom.habits.future')]);
        }
    }
}
