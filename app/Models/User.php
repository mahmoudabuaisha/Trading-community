<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * `role` and `is_premium` are deliberately NOT fillable: they can only change through
 * App\Services\AdminService, never through mass assignment at sign-up.
 */
#[Fillable(['display_name', 'surname', 'nickname', 'email', 'password', 'avatar_url', 'bio', 'timezone', 'language', 'weekly_goal', 'daily_focus_minutes', 'specialties'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    /** Staff with a chosen subset of the admin center (see PERMISSIONS). */
    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_USER = 'user';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_MODERATOR, self::ROLE_USER];

    /** Areas of the admin center. An administrator has all of them; a moderator has those granted. */
    public const PERMISSIONS = ['users', 'content', 'growth', 'education', 'badges', 'store', 'messages', 'audit'];

    /** Areas a moderator can be given. Members' private messages are not among them. */
    public const GRANTABLE = ['users', 'content', 'growth', 'education', 'badges', 'store', 'audit'];

    public const DEFAULT_PREFERENCES = [
        'notifyReminders' => true,
        'notifyMorning' => true,
        'notifyInactivity' => true,
        'notifySleep' => false,
        'sleepTime' => null,
        'wakeTime' => null,
    ];

    /** Starter categories every account gets, whichever path creates it. */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'تداول', 'color' => '#70d6ad'],
        ['name' => 'تعلم', 'color' => '#a89bf5'],
        ['name' => 'شخصي', 'color' => '#f2c879'],
        ['name' => 'عمل', 'color' => '#83d8f5'],
    ];

    protected $attributes = [
        'role' => self::ROLE_USER,
        'surname' => '',
        'bio' => '',
        'timezone' => 'Asia/Gaza',
        'language' => 'ar',
        'weekly_goal' => 20,
        'daily_focus_minutes' => 120,
    ];

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->categories()->createMany(self::DEFAULT_CATEGORIES);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'preferences' => 'array',
            'equipped' => 'array',
            'specialties' => 'array',
            'designs' => 'array',
            'last_seen_at' => 'datetime',
            'is_premium' => 'boolean',
            'premium_until' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'weekly_goal' => 'integer',
            'daily_focus_minutes' => 'integer',
        ];
    }

    /** Subscriber-only lessons are open to premium members and to administrators. */
    /** Switched on by an administrator and not past its end date (none means it lasts). */
    public function hasActiveSubscription(): bool
    {
        return (bool) $this->is_premium && ($this->premium_until === null || $this->premium_until->isFuture());
    }

    /**
     * May this member use one paid service? Staff always may; a member needs an
     * active subscription and that service opened for them.
     */
    public function hasGrant(string $type, int $id): bool
    {
        if ($this->isStaff()) {
            return true;
        }

        return $this->hasActiveSubscription() && $this->subscriptionGrants()->where('grantable_type', $type)->where('grantable_id', $id)->exists();
    }

    /** @return list<int> ids of one kind of service the member may use now (empty without an active subscription) */
    public function grantedIds(string $type): array
    {
        if (! $this->hasActiveSubscription()) {
            return [];
        }

        return $this->subscriptionGrants()->where('grantable_type', $type)->pluck('grantable_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function subscriptionGrants(): HasMany
    {
        return $this->hasMany(SubscriptionGrant::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MODERATOR], true);
    }

    /** May this user work in the given area of the admin center? */
    public function hasPermission(string $area): bool
    {
        // Members write to the administration in confidence: only full administrators read it.
        if ($area === 'messages') {
            return $this->isAdmin();
        }

        return $this->isAdmin() || ($this->role === self::ROLE_MODERATOR && in_array($area, $this->permissions ?? [], true));
    }

    /**
     * The areas this user can actually use, in canonical order.
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        return array_values(array_filter(self::PERMISSIONS, fn (string $area): bool => $this->hasPermission($area)));
    }

    /**
     * Stored preferences merged over the defaults, so a new option is never missing.
     *
     * @return array<string, mixed>
     */
    public function preferenceValues(): array
    {
        return array_merge(self::DEFAULT_PREFERENCES, array_intersect_key($this->preferences ?? [], self::DEFAULT_PREFERENCES));
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** What a member can say they do; shown on their profile. */
    public const SPECIALTIES = [
        'student_school', 'student_university', 'trader_learning', 'trader_beginner', 'trader_pro',
        'merchant', 'freelancer', 'employee', 'business_owner', 'content_creator', 'other',
    ];

    public function designPurchases(): HasMany
    {
        return $this->hasMany(DesignPurchase::class);
    }

    public function dailyTipDraws(): HasMany
    {
        return $this->hasMany(DailyTipDraw::class);
    }

    public function dayEntries(): HasMany
    {
        return $this->hasMany(DayEntry::class);
    }

    public function visionBoards(): HasMany
    {
        return $this->hasMany(VisionBoard::class);
    }

    public function timelineEntries(): HasMany
    {
        return $this->hasMany(TimelineEntry::class);
    }

    public function portfolioProjects(): HasMany
    {
        return $this->hasMany(PortfolioProject::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function focusSessions(): HasMany
    {
        return $this->hasMany(FocusSession::class);
    }

    public function dailyReviews(): HasMany
    {
        return $this->hasMany(DailyReview::class);
    }

    /** The member's private thread with the administrators. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    public function moodEntries(): HasMany
    {
        return $this->hasMany(MoodEntry::class);
    }

    public function habitNotes(): HasMany
    {
        return $this->hasMany(HabitNote::class);
    }
}
