<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // Monthly goal: how many days this habit should be done. Null means "every day".
            $table->unsignedTinyInteger('target_days')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });

        // One row per day a habit was done; absence means "not done".
        Schema::create('habit_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->timestamps();

            $table->unique(['habit_id', 'day']);
        });

        // Daily self-rating, each 1–10.
        Schema::create('mood_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedTinyInteger('mood')->nullable();
            $table->unsignedTinyInteger('motivation')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'day']);
        });

        Schema::create('habit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7); // YYYY-MM
            $table->text('body');
            $table->timestamps();

            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_notes');
        Schema::dropIfExists('mood_entries');
        Schema::dropIfExists('habit_checks');
        Schema::dropIfExists('habits');
    }
};
