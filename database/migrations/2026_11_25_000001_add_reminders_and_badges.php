<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            // When the owner wants to be reminded, and when they dismissed that reminder.
            $table->dateTime('remind_at')->nullable()->index();
            $table->dateTime('reminder_dismissed_at')->nullable();
        });

        // Badges defined by administrators, awarded automatically when a member's
        // metric reaches the threshold. Earned ones are stored as achievements
        // under the key "badge:{id}".
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 80);
            $table->string('description', 240)->default('');
            $table->string('metric', 32);
            $table->unsignedInteger('threshold');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['remind_at']);
            $table->dropColumn(['remind_at', 'reminder_dismissed_at']);
        });
    }
};
