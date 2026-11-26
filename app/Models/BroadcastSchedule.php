<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** A message to every member that repeats daily or weekly at a set time. */
class BroadcastSchedule extends Model
{
    use Translatable;

    public const TRANSLATABLE = ['body'];

    public const FREQUENCIES = ['daily', 'weekly'];

    protected $fillable = ['frequency', 'weekday', 'time', 'body', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * Today's occurrences whose time has come, in the schedule timezone.
     * Each carries an id unique to its day, so a member is told once per occurrence.
     *
     * @return list<array{id: string, kind: string, body: string}>
     */
    public static function dueNow(?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(config('app.schedule_timezone'));

        return self::where('is_active', true)->where('time', '<=', $now->format('H:i'))
            ->where(fn ($query) => $query->where('frequency', 'daily')->orWhere(fn ($weekly) => $weekly->where('frequency', 'weekly')->where('weekday', $now->dayOfWeekIso)))
            ->orderBy('time')->get()
            ->map(fn (BroadcastSchedule $schedule): array => ['id' => "r{$schedule->id}-{$now->toDateString()}", 'kind' => 'recurring', 'body' => $schedule->tr('body')])->all();
    }
}
