<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\StoreItem;
use App\Models\SubscriptionGrant;
use App\Models\User;
use App\Services\AdminService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subscriptions are paid outside the site: an administrator switches one on,
 * optionally until a date, and picks which paid services it opens.
 */
class SubscriptionController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json($this->payload($user));
    }

    public function update(Request $request, User $user, AdminService $admin): JsonResponse
    {
        $data = $request->validate([
            'isPremium' => ['required', 'boolean'],
            // The last day of the subscription, in the platform's schedule timezone; none means no end.
            'premiumUntil' => ['present', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            // A preset tier key or the admin's own name for this membership.
            'tier' => ['sometimes', 'nullable', 'string', 'max:40'],
            'grants' => ['present', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['integer'],
        ]);
        $until = $data['premiumUntil'] ? CarbonImmutable::parse($data['premiumUntil'], config('app.schedule_timezone'))->endOfDay()->utc() : null;
        $wanted = [
            'lesson' => Lesson::where('is_premium', true)->whereIn('id', $data['grants']['lesson'] ?? [])->pluck('id')->all(),
            'book' => Book::whereIn('id', $data['grants']['book'] ?? [])->pluck('id')->all(),
            'store_item' => StoreItem::where('is_exclusive', true)->whereIn('id', $data['grants']['store_item'] ?? [])->pluck('id')->all(),
            // The AI assistant is one service with no id of its own.
            'assistant' => in_array(0, $data['grants']['assistant'] ?? [], true) ? [0] : [],
        ];

        DB::transaction(function () use ($request, $user, $admin, $data, $until, $wanted): void {
            $wasOn = $user->hasActiveSubscription();
            $tier = trim((string) ($data['tier'] ?? ''));
            $user->forceFill(['is_premium' => $data['isPremium'], 'premium_until' => $data['isPremium'] ? $until : null, 'premium_tier' => $data['isPremium'] && $tier !== '' ? $tier : null])->save();
            $user->subscriptionGrants()->delete();
            foreach ($wanted as $type => $ids) {
                foreach ($ids as $id) {
                    $user->subscriptionGrants()->create(['grantable_type' => $type, 'grantable_id' => $id]);
                }
            }
            $action = $data['isPremium'] ? ($wasOn ? 'subscription_updated' : 'premium_granted') : 'premium_revoked';
            $admin->recordFor($request->user(), $action, $user, [
                'until' => $until?->toISOString(),
                'services' => array_sum(array_map('count', $wanted)),
            ]);
        });

        return response()->json($this->payload($user->refresh()));
    }

    /** The automatic note members see when they open their private conversation. */
    public function notice(): JsonResponse
    {
        return response()->json(['notice' => Setting::where('key', 'subscription_notice')->value('value') ?? '']);
    }

    public function saveNotice(Request $request, AdminService $admin): JsonResponse
    {
        $text = trim((string) $request->validate(['notice' => ['present', 'nullable', 'string', 'max:1500']])['notice']);
        if ($text === '') {
            Setting::where('key', 'subscription_notice')->delete();
        } else {
            Setting::updateOrCreate(['key' => 'subscription_notice'], ['value' => $text]);
        }
        $admin->record($request->user(), 'subscription_notice_updated', mb_strimwidth($text, 0, 60, '…'));

        return response()->json(['notice' => $text]);
    }

    private function payload(User $user): array
    {
        $grants = $user->subscriptionGrants()->get()->groupBy('grantable_type')->map(fn ($rows) => $rows->pluck('grantable_id')->map(fn ($id): int => (int) $id)->values());

        return [
            'isPremium' => (bool) $user->is_premium,
            'tier' => $user->premium_tier,
            'active' => $user->hasActiveSubscription(),
            // The last day as the admin picked it (schedule timezone).
            'premiumUntil' => $user->premium_until?->setTimezone(config('app.schedule_timezone'))->toDateString(),
            'grants' => collect(SubscriptionGrant::TYPES)->mapWithKeys(fn (string $type): array => [$type => $grants[$type] ?? []]),
            'options' => [
                'lesson' => Lesson::where('is_premium', true)->orderBy('position')->get(['id', 'title']),
                'book' => Book::orderBy('position')->get(['id', 'title']),
                'store_item' => StoreItem::where('is_exclusive', true)->orderBy('price')->get(['id', 'name as title']),
                'assistant' => [['id' => 0, 'title' => __('validation.custom.assistant.service')]],
            ],
        ];
    }
}
