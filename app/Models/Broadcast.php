<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message from the administrators shown to every member as a notification. */
class Broadcast extends Model
{
    /** One per calendar day, written ahead of time. */
    public const KIND_SCHEDULED = 'scheduled';

    /** Sent on the spot. */
    public const KIND_INSTANT = 'instant';

    protected $fillable = ['kind', 'day', 'body', 'created_by'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    /**
     * What a member should see on their local `day`.
     *
     * @return list<array{id: int, kind: string, body: string}>
     */
    public static function forDay(string $day): array
    {
        return self::whereDate('day', $day)->orderBy('id')->get()
            ->map(fn (Broadcast $item): array => ['id' => $item->id, 'kind' => $item->kind, 'body' => $item->body])->all();
    }
}
