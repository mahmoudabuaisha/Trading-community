<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nickname', 120)->default('');
            // Granted by an administrator; opens the subscriber-only lessons.
            $table->boolean('is_premium')->default(false)->index();
        });

        Schema::table('lessons', function (Blueprint $table): void {
            // Subscriber-only lessons are listed for everyone but readable only by premium members.
            $table->boolean('is_premium')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('is_premium'));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_premium']);
            $table->dropColumn(['nickname', 'is_premium']);
        });
    }
};
