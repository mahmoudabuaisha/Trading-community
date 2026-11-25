<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Areas of the admin center a moderator may use (see User::PERMISSIONS). Ignored for other roles.
            $table->json('permissions')->nullable();
            // Per-account notification choices and sleep/wake times (see User::DEFAULT_PREFERENCES).
            $table->json('preferences')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            // Rewards the member has equipped; what they may equip depends on their points.
            $table->string('frame', 24)->default('none');
            $table->string('title_key', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['permissions', 'preferences', 'last_seen_at', 'frame', 'title_key']));
    }
};
