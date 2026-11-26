<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A design for the goal map or the skill tree: one of the built-in
        // layouts dressed in a style (accent colour, node shape, background).
        // Price 0 means free for everyone.
        Schema::create('designs', function (Blueprint $table): void {
            $table->id();
            $table->string('target', 16);          // goal_map | skill_tree
            $table->string('layout', 16);          // built-in layout key, see App\Models\Design::LAYOUTS
            $table->string('name', 80);
            $table->string('description', 240)->default('');
            $table->unsignedInteger('price')->default(0);
            $table->string('accent', 7)->default('#d6ad62');
            $table->string('node_shape', 12)->default('circle');
            $table->string('background_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('design_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->timestamps();
            $table->unique(['user_id', 'design_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            // The design in use per target: {"goal_map": 2, "skill_tree": 5}.
            $table->json('designs')->nullable();
        });

        $now = now();
        $rows = [
            ['goal_map', 'mindmap', 'الخريطة الذهنية', 'الهدف في المنتصف ومراحله تتفرع من حوله.', 0, '#d6ad62', 'circle', 1],
            ['goal_map', 'branches', 'الخريطة المتفرعة', 'الهدف في البداية وفروعه تمتد خطوة بعد خطوة.', 0, '#83d8f5', 'rounded', 2],
            ['goal_map', 'orbit', 'المجرّة', 'مراحلك تدور حول هدفك كالكواكب.', 150, '#a89bf5', 'star', 3],
            ['goal_map', 'mindmap', 'الخريطة الملكية', 'الخريطة الذهنية بلمسة ذهبية ونقاط سداسية.', 100, '#f2c879', 'hexagon', 4],
            ['skill_tree', 'tree', 'شجرة المهارات', 'كل مستوى ثمرة على الشجرة، تنضج عندما تجتازه.', 0, '#e58a7b', 'apple', 1],
            ['skill_tree', 'pyramid', 'الهرم', 'المهارة الأساسية في القمة ومستوياتها تتفرع تحتها.', 0, '#d6ad62', 'circle', 2],
            ['skill_tree', 'stairs', 'سلّم الصعود', 'كل مستوى درجة تصعدها نحو القمة.', 120, '#79cda4', 'diamond', 3],
            ['skill_tree', 'tree', 'الشجرة الذهبية', 'ثمار ذهبية على شجرة ملكية.', 200, '#f2c879', 'star', 4],
        ];
        foreach ($rows as [$target, $layout, $name, $description, $price, $accent, $shape, $position]) {
            DB::table('designs')->insert(['target' => $target, 'layout' => $layout, 'name' => $name, 'description' => $description, 'price' => $price, 'accent' => $accent, 'node_shape' => $shape, 'position' => $position, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('designs'));
        Schema::dropIfExists('design_purchases');
        Schema::dropIfExists('designs');
    }
};
