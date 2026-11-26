<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Members' messages are read by full administrators only, so the
        // "messages" area can no longer be handed to a moderator.
        foreach (DB::table('users')->whereNotNull('permissions')->get(['id', 'permissions']) as $user) {
            $permissions = array_values(array_diff(json_decode($user->permissions, true) ?: [], ['messages']));
            DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($permissions)]);
        }

        // Messages that repeat: every day at a time, or every week on a weekday
        // at a time. Times are wall-clock times in the platform's schedule
        // timezone (config app.schedule_timezone).
        Schema::create('broadcast_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('frequency', 8);                 // daily | weekly
            $table->unsignedTinyInteger('weekday')->nullable(); // 1 = Monday … 7 = Sunday; weekly only
            $table->string('time', 5);                       // HH:MM
            $table->string('body', 500);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_schedules');
    }
};
