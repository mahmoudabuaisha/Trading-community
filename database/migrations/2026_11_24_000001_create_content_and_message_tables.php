<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-editable interface copy (dashboard tagline, welcome line), one row per key.
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->string('value', 500)->nullable();
            $table->timestamps();
        });

        // One private thread per member with the administrators. `user_id` is the
        // member who owns the thread; `from_admin` says which side wrote the message.
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['from_admin', 'read_at']);
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('settings');
    }
};
