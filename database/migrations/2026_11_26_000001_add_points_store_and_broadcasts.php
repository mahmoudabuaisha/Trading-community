<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Achievements become repeatable and carry points: a badge has a period
        // (once, daily, weekly, monthly, yearly), a list of conditions that must
        // all hold within that period, and the points it awards each time.
        Schema::table('badges', function (Blueprint $table): void {
            $table->unsignedInteger('points')->default(0);
            $table->string('period', 16)->default('once');
            $table->json('conditions')->nullable();
        });
        foreach (DB::table('badges')->get() as $badge) {
            DB::table('badges')->where('id', $badge->id)->update([
                'conditions' => json_encode([['metric' => $badge->metric, 'threshold' => (int) $badge->threshold]]),
            ]);
        }
        Schema::table('badges', function (Blueprint $table): void {
            $table->dropColumn(['metric', 'threshold']);
        });

        // One row each time a member earns a badge. `period_key` identifies the
        // period ("once", "2026-11-25", "W2026-11-23", "2026-11", "2026"), and the
        // unique index makes awarding idempotent. Points are copied at award time,
        // so later edits to the badge do not rewrite history.
        Schema::create('badge_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 16);
            $table->unsignedInteger('points')->default(0);
            $table->date('day');
            $table->timestamps();
            $table->unique(['user_id', 'badge_id', 'period_key']);
            $table->index(['user_id', 'day']);
            $table->index('day');
        });
        // Badges earned so far were stored as achievements under "badge:{id}".
        foreach (DB::table('achievements')->where('key', 'like', 'badge:%')->get() as $row) {
            $badgeId = (int) substr($row->key, 6);
            if (DB::table('badges')->where('id', $badgeId)->exists()) {
                DB::table('badge_awards')->insert([
                    'user_id' => $row->user_id, 'badge_id' => $badgeId, 'period_key' => 'once', 'points' => 0,
                    'day' => substr((string) $row->earned_at, 0, 10), 'created_at' => $row->earned_at, 'updated_at' => $row->earned_at,
                ]);
            }
        }
        DB::table('achievements')->where('key', 'like', 'badge:%')->delete();

        // What members can buy with their points. `kind` says what the item is
        // (title, frame, theme, avatar) and `value` what it applies: the title's
        // text, or the key of a built-in frame style or theme.
        Schema::create('store_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('description', 240)->default('');
            $table->string('kind', 16);
            $table->string('value', 80)->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('store_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->timestamps();
            $table->unique(['user_id', 'store_item_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['frame', 'title_key']);
            // Which purchased item is in use per kind: {"title": 3, "frame": 7, "theme": null}.
            $table->json('equipped')->nullable();
        });

        // Messages from the administrators to every member: one scheduled per
        // calendar day, plus ones sent on the spot.
        Schema::create('broadcasts', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->date('day')->index();
            $table->string('body', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Starting content, all editable or removable from the admin center.
        $now = now();
        DB::table('badges')->insert([
            'title' => 'يوم مكتمل', 'description' => 'أنجز كل مهامك اليومية لهذا اليوم.', 'points' => 10, 'period' => 'daily',
            'conditions' => json_encode([['metric' => 'perfect_days', 'threshold' => 1]]), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $items = [
            ['إطار برونزي', 'إطار برونزي حول صورتك الشخصية.', 'frame', 'bronze', 30],
            ['إطار فضي', 'إطار فضي حول صورتك الشخصية.', 'frame', 'silver', 100],
            ['إطار ذهبي', 'إطار ذهبي مميز حول صورتك الشخصية.', 'frame', 'gold', 250],
            ['إطار ملكي', 'إطار ملكي فاخر حول صورتك الشخصية.', 'frame', 'royal', 500],
            ['لقب الملتزم', 'لقب يظهر تحت اسمك.', 'title', 'الملتزم', 50],
            ['لقب المنجز', 'لقب يظهر تحت اسمك.', 'title', 'المنجز', 150],
            ['لقب القائد', 'لقب يظهر تحت اسمك.', 'title', 'القائد', 400],
            ['لقب الأسطورة', 'لقب حصري يظهر تحت اسمك.', 'title', 'الأسطورة', 800],
        ];
        foreach ($items as [$name, $description, $kind, $value, $price]) {
            DB::table('store_items')->insert(compact('name', 'description', 'kind', 'value', 'price') + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('equipped');
            $table->string('frame', 24)->default('none');
            $table->string('title_key', 32)->nullable();
        });
        Schema::dropIfExists('store_purchases');
        Schema::dropIfExists('store_items');
        Schema::dropIfExists('badge_awards');
        Schema::table('badges', function (Blueprint $table): void {
            $table->string('metric', 32)->default('tasks_completed');
            $table->unsignedInteger('threshold')->default(1);
            $table->dropColumn(['points', 'period', 'conditions']);
        });
    }
};
