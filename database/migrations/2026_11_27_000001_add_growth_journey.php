<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // What the member does (machine keys: student_university, trader_beginner, …).
            $table->json('specialties')->nullable();
            // The portfolio is reachable at /p/{slug} when its visibility allows.
            $table->string('portfolio_slug', 32)->nullable()->unique();
            $table->string('portfolio_visibility', 12)->default('private'); // private | members | public
        });

        // A goal's roadmap: stages (parent_id null) and the steps inside each.
        Schema::create('goal_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('goal_steps')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('requirements', 500)->default('');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // The member's own words on their timeline: a note on an automatic
        // milestone (event_key set) or a milestone they added themselves.
        Schema::create('timeline_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64)->nullable();
            $table->date('day')->nullable();
            $table->string('title', 180)->nullable();
            $table->string('note', 1000)->default('');
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
        });

        Schema::create('vision_boards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->string('area', 24)->default('personal');
            $table->string('color', 16)->default('gold');
            $table->timestamps();
        });
        Schema::create('vision_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vision_board_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12); // image | quote | dream
            $table->string('text', 500)->default('');
            $table->string('image_path')->nullable();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('size', 4)->default('md'); // sm | md | lg
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Skill paths are written by the administrators; each level may link a
        // lesson, set a practical challenge and carry a quiz.
        Schema::create('skill_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('description', 500)->default('');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('skill_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_path_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title', 120);
            $table->text('description');
            $table->string('challenge', 500)->default('');
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            // [{question, options: [..], answer: index}]
            $table->json('quiz')->nullable();
            $table->timestamps();
        });
        Schema::create('skill_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_level_id')->constrained()->cascadeOnDelete();
            $table->dateTime('passed_at');
            $table->unique(['user_id', 'skill_level_id']);
        });

        Schema::create('portfolio_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->string('category', 24)->default('other');
            $table->json('skills')->nullable();
            $table->string('link', 500)->nullable();
            $table->string('image_path')->nullable();
            $table->date('completed_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['portfolio_projects', 'skill_progress', 'skill_levels', 'skill_paths', 'vision_items', 'vision_boards', 'timeline_entries', 'goal_steps'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['portfolio_slug']);
            $table->dropColumn(['specialties', 'portfolio_slug', 'portfolio_visibility']);
        });
    }
};
