<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['user_id', 'sender_id', 'from_admin', 'body', 'read_at'];

    protected function casts(): array
    {
        return ['from_admin' => 'boolean', 'read_at' => 'datetime'];
    }

    /** The member whose thread this message belongs to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Messages the given side has not opened yet: admins read members' messages and vice versa. */
    public function scopeUnreadFor(Builder $query, bool $admin): void
    {
        $query->whereNull('read_at')->where('from_admin', ! $admin);
    }
}
