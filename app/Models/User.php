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
 * `role` is deliberately NOT fillable: it can only change through
 * App\Services\AdminService, never through mass assignment at sign-up.
 */
#[Fillable(['display_name', 'surname', 'email', 'password', 'avatar_url', 'bio', 'timezone', 'language', 'weekly_goal', 'daily_focus_minutes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_USER];

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'weekly_goal' => 'integer',
            'daily_focus_minutes' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
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
}
