<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_paths', function (Blueprint $table) {
            // Set: a member's own path, private to them. Null: written by the administrators for everyone.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('skill_paths', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
