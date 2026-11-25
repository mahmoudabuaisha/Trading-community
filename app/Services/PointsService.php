<?php

namespace App\Services;

use App\Models\BadgeAward;
use App\Models\StoreItem;
use App\Models\StorePurchase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Points are never stored as a number that could drift: what a member earned
 * is the sum of their badge awards, what they spent is the sum of their
 * purchases, and the balance is the difference.
 */
class PointsService
{
    public function earned(User $user): int
    {
        return (int) BadgeAward::where('user_id', $user->id)->sum('points');
    }

    public function balance(User $user): int
    {
        return $this->earned($user) - (int) StorePurchase::where('user_id', $user->id)->sum('price');
    }

    /**
     * Points earned per day over the last `$days` local days, oldest first.
     *
     * @return list<array{day: string, points: int}>
     */
    public function history(User $user, string $today, int $days = 30): array
    {
        $end = CarbonImmutable::parse($today);
        $start = $end->subDays($days - 1);
        $byDay = BadgeAward::where('user_id', $user->id)->whereDate('day', '>=', $start->toDateString())
            ->selectRaw('day, SUM(points) as total')->groupBy('day')->get()
            ->mapWithKeys(fn ($row): array => [substr((string) $row->getRawOriginal('day'), 0, 10) => (int) $row->total]);

        $history = [];
        for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
            $history[] = ['day' => $cursor->toDateString(), 'points' => $byDay[$cursor->toDateString()] ?? 0];
        }

        return $history;
    }

    /** Spend points on a store item. The row lock makes two simultaneous purchases queue up. */
    public function buy(User $user, StoreItem $item): void
    {
        DB::transaction(function () use ($user, $item): void {
            User::whereKey($user->id)->lockForUpdate()->first();
            if (! $item->is_active) {
                throw ValidationException::withMessages(['item' => __('validation.custom.store.unavailable')]);
            }
            if (StorePurchase::where('user_id', $user->id)->where('store_item_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['item' => __('validation.custom.store.owned')]);
            }
            if ($this->balance($user) < $item->price) {
                throw ValidationException::withMessages(['item' => __('validation.custom.store.points')]);
            }
            StorePurchase::create(['user_id' => $user->id, 'store_item_id' => $item->id, 'price' => $item->price]);
        });
    }

    /**
     * Put a purchased item to use, or (with null) stop using that kind.
     * An avatar item replaces the profile photo instead of being "worn".
     */
    public function equip(User $user, string $kind, ?StoreItem $item): void
    {
        if ($item !== null) {
            $owned = StorePurchase::where('user_id', $user->id)->where('store_item_id', $item->id)->exists();
            if (! $owned || $item->kind !== $kind) {
                throw ValidationException::withMessages(['item' => __('validation.custom.store.notOwned')]);
            }
        }
        // A product is not worn; it is downloaded or opened.
        if ($kind === 'product') {
            throw ValidationException::withMessages(['item' => __('validation.custom.store.notOwned')]);
        }
        if ($kind === 'avatar') {
            $user->forceFill(['avatar_url' => $item?->imageUrl()])->save();

            return;
        }
        $user->forceFill(['equipped' => [...($user->equipped ?? []), $kind => $item?->id]])->save();
    }

    /**
     * What the member currently shows, resolved from the items they have in use.
     * An item that was deleted simply stops showing.
     *
     * @return array{title: string|null, frame: string, theme: string}
     */
    public function appearance(User $user): array
    {
        $ids = array_filter($user->equipped ?? []);
        $items = $ids ? StoreItem::whereIn('id', $ids)->get()->keyBy('kind') : collect();

        return [
            'title' => $items->get('title')?->tr('value'),
            'frame' => $items->get('frame')?->value ?? 'none',
            'theme' => $items->get('theme')?->value ?? 'gold',
        ];
    }
}
