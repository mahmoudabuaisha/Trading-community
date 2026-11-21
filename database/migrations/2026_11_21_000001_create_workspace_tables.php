<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace schema.
 *
 * Conventions
 * - Statuses, priorities, period types, categories and achievement keys are
 *   machine values; Arabic / English labels live in the UI layer only.
 * - Calendar days are DATE columns; instants are DATETIME columns (UTC).
 * - Durations are whole minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 9);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A task outlives its category: deleting a category only un-files the task.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->string('period_type', 10);   // daily | weekly | monthly | yearly
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->default('medium'); // low | medium | high | urgent
            $table->string('status', 12)->default('pending');  // pending | in_progress | completed | cancelled
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_type', 'period_start']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->string('period_type', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('target_value');
            $table->unsignedInteger('current_value')->default(0);
            $table->string('status', 12)->default('active'); // active | completed
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->dateTime('earned_at');

            // A badge is earned once; the database refuses a duplicate on its own.
            $table->unique(['user_id', 'key']);
        });

        Schema::create('focus_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes');
            // The user's local calendar day the session belongs to.
            $table->date('day');
            $table->timestamps();

            $table->index(['user_id', 'day']);
        });

        Schema::create('daily_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('win');
            $table->text('unfinished');
            $table->text('lesson');
            $table->text('tomorrow');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('motivations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('body');
            $table->string('category', 20)->default('growth'); // growth | motivation | announcement
            $table->boolean('is_active')->default(true)->index();
            // Content stays published when its author's account is removed.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_audits', function (Blueprint $table) {
            $table->id();
            // The audit trail is history: it survives the accounts it mentions.
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        foreach (['admin_audits', 'motivations', 'daily_reviews', 'focus_sessions', 'achievements', 'goals', 'tasks', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
