<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            // A store item (badge next to the name, frame, title…) handed over free with the achievement.
            $table->foreignId('reward_item_id')->nullable()->constrained('store_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reward_item_id');
        });
    }
};
