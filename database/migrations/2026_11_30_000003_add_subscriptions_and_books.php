<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A subscription is paid outside the site and switched on by hand. With
        // no end date it lasts until it is switched off.
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('premium_until')->nullable();
        });

        // Which paid services an administrator opened for which member:
        // a subscriber-only lesson, a book, or a store item reserved to them.
        Schema::create('subscription_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('grantable_type', 16);   // lesson | book | store_item
            $table->unsignedBigInteger('grantable_id');
            $table->timestamps();
            $table->unique(['user_id', 'grantable_type', 'grantable_id'], 'subscription_grants_unique');
            $table->index(['grantable_type', 'grantable_id']);
        });

        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('author', 120)->default('');
            $table->text('description');
            $table->string('cover_path')->nullable();
            // The book itself lives on the private disk and is sent only to members it was opened for.
            $table->string('file_path')->nullable();
            $table->string('file_name', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A store item that only the members it was opened for can buy.
        Schema::table('store_items', function (Blueprint $table): void {
            $table->boolean('is_exclusive')->default(false);
        });

        // Until now a subscriber read every subscriber-only lesson. Keep that for
        // the members who already subscribed, now as explicit grants.
        $now = now();
        $lessons = DB::table('lessons')->where('is_premium', true)->pluck('id');
        foreach (DB::table('users')->where('is_premium', true)->pluck('id') as $userId) {
            foreach ($lessons as $lessonId) {
                DB::table('subscription_grants')->insert(['user_id' => $userId, 'grantable_type' => 'lesson', 'grantable_id' => $lessonId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('store_items', fn (Blueprint $table) => $table->dropColumn('is_exclusive'));
        Schema::dropIfExists('books');
        Schema::dropIfExists('subscription_grants');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('premium_until'));
    }
};
